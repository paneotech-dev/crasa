<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BlockType;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageBlock;
use App\Services\PageVersioning;
use App\Support\Locales;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Adding, arranging and editing the blocks on a page.
 *
 * REORDERING IS BUTTONS, NOT DRAGGING. Screen 14 draws content as rows in a
 * panel and never draws a canvas, so there is nothing to match for a
 * drag-and-drop editor. Two buttons on a row also happen to be the only
 * version of reordering that works from a keyboard without building a
 * bespoke widget with its own announcements, which matters because WCAG 2.1
 * AA is an acceptance criterion here rather than an aspiration.
 *
 * EVERY SETTING IS VALIDATED ON THE SERVER, TWICE. Once here against
 * BlockType::settingsRules(), and again on the model, which validates on
 * every save whatever route reached it. An editor cannot give a pillars
 * block five columns or a call to action an arbitrary colour by editing the
 * form.
 */
class PageBlockController extends Controller
{
    public function __construct(private readonly PageVersioning $versioning) {}

    public function create(Page $page): View
    {
        Gate::authorize('create', PageBlock::class);

        return view('pages.admin.pages.add-block', [
            'page' => $page,
            'types' => BlockType::cases(),
        ]);
    }

    public function store(Request $request, Page $page): RedirectResponse
    {
        Gate::authorize('create', PageBlock::class);

        $data = $request->validate([
            'block_type' => ['required', Rule::enum(BlockType::class)],
        ]);

        $type = BlockType::from($data['block_type']);

        $block = PageBlock::create([
            'page_id' => $page->id,
            'block_type' => $type,
            'position' => ((int) $page->blocks()->max('position')) + 1,
            // A new block arrives hidden. Adding one to a published page
            // should not put an empty panel in front of fourteen regulators
            // between the click that creates it and the one that fills it.
            'is_visible' => false,
            'settings' => $type->defaultSettings(),
        ]);

        $this->versioning->record($page, __('admin.page_version_block_added', ['type' => $type->label()]));

        return redirect()
            ->route('admin.pages.blocks.edit', [
                'locale' => app()->getLocale(), 'page' => $page->slug, 'block' => $block->id,
            ])
            ->with('status', __('admin.block_added'));
    }

    public function edit(Page $page, PageBlock $block): View
    {
        Gate::authorize('update', $block);
        abort_unless($block->page_id === $page->id, 404);

        $block->load('translations');

        return view('pages.admin.pages.edit-block', [
            'page' => $page,
            'block' => $block,
            'type' => $block->type(),
            'locales' => Locales::SUPPORTED,
        ]);
    }

    public function update(Request $request, Page $page, PageBlock $block): RedirectResponse
    {
        Gate::authorize('update', $block);
        abort_unless($block->page_id === $page->id, 404);

        $type = $block->type();

        // The block type's own rules, namespaced under settings so the error
        // messages point at the field the editor is looking at.
        $rules = ['needs_content' => ['nullable', 'boolean'], 'content_note' => ['nullable', 'string', 'max:500']];

        foreach ($type->settingsRules() as $key => $rule) {
            $rules['settings.'.$key] = $rule;
        }

        foreach (Locales::SUPPORTED as $locale) {
            foreach ($type->translatableFields() as $field) {
                $rules[$locale.'.'.$field] = ['nullable', 'string', 'max:'.$type->maxLengthFor($field)];
            }

            // Key figures carry a label and a source per item, and both are
            // text, so both are per-language. The figure itself is not.
            if ($type === BlockType::KeyFigures) {
                $rules[$locale.'.*'] = ['nullable', 'string', 'max:4000'];
            }
        }

        /*
         * The spare row the form always renders is dropped before
         * validation, not after. A rule like items.*.value => required
         * would otherwise reject the empty row the editor never touched,
         * and the error would point at a field they cannot see.
         */
        $request->merge(['settings' => $this->clean($request->input('settings', []))]);

        $data = $request->validate($rules);

        $this->versioning->recordInitial($page);

        $settings = $data['settings'] ?? [];

        $block->update([
            'settings' => $settings,
            'needs_content' => (bool) ($data['needs_content'] ?? false),
            'content_note' => $data['content_note'] ?? null,
        ]);

        foreach (Locales::SUPPORTED as $locale) {
            $block->setTranslations($locale, array_filter(
                $data[$locale] ?? [],
                fn ($value) => $value !== null && trim((string) $value) !== ''
            ));
        }

        $this->versioning->record($page, __('admin.page_version_block_edited', ['type' => $type->label()]));

        return redirect()
            ->route('admin.pages.edit', ['locale' => app()->getLocale(), 'page' => $page->slug])
            ->with('status', __('admin.block_saved'));
    }

    /**
     * Move one place up or down.
     *
     * Positions are rewritten for the whole page in a transaction rather
     * than swapped in place. Swapping works until two blocks share a
     * position, which they will the first time a seeder or an import gets
     * it slightly wrong, and then the order silently depends on insertion
     * order.
     */
    public function move(Request $request, Page $page, PageBlock $block): RedirectResponse
    {
        Gate::authorize('update', $block);
        abort_unless($block->page_id === $page->id, 404);

        $direction = $request->validate([
            'direction' => ['required', 'in:up,down'],
        ])['direction'];

        DB::transaction(function () use ($page, $block, $direction) {
            $ordered = $page->blocks()->orderBy('position')->orderBy('id')->get();
            $index = $ordered->search(fn (PageBlock $candidate) => $candidate->id === $block->id);

            if ($index === false) {
                return;
            }

            $target = $direction === 'up' ? $index - 1 : $index + 1;

            if ($target < 0 || $target >= $ordered->count()) {
                return;
            }

            $reordered = $ordered->values()->all();
            [$reordered[$index], $reordered[$target]] = [$reordered[$target], $reordered[$index]];

            foreach ($reordered as $position => $record) {
                $record->update(['position' => $position]);
            }
        });

        return back()->with('status', __('admin.block_moved'));
    }

    /**
     * A copy, hidden, immediately after the original.
     *
     * The copy carries the original's translations. A duplicate that loses
     * the French and Portuguese is not a duplicate, it is a new block with
     * an English draft, and the editor finds out three weeks later.
     */
    public function duplicate(Page $page, PageBlock $block): RedirectResponse
    {
        Gate::authorize('create', PageBlock::class);
        abort_unless($block->page_id === $page->id, 404);

        $copy = DB::transaction(function () use ($page, $block) {
            $page->blocks()
                ->where('position', '>', $block->position)
                ->orderByDesc('position')
                ->get()
                ->each(fn (PageBlock $later) => $later->update(['position' => $later->position + 1]));

            $copy = PageBlock::create([
                'page_id' => $page->id,
                'block_type' => $block->block_type,
                'position' => $block->position + 1,
                'is_visible' => false,
                'settings' => $block->settings,
                'is_sample' => $block->is_sample,
                'needs_content' => $block->needs_content,
                'content_note' => $block->content_note,
            ]);

            foreach ($block->translations as $translation) {
                $copy->setTranslations($translation->locale, [$translation->field => $translation->value]);
            }

            return $copy;
        });

        return redirect()
            ->route('admin.pages.blocks.edit', [
                'locale' => app()->getLocale(), 'page' => $page->slug, 'block' => $copy->id,
            ])
            ->with('status', __('admin.block_duplicated'));
    }

    /** Hiding is not deleting. A hidden block keeps its copy and its place. */
    public function toggle(Page $page, PageBlock $block): RedirectResponse
    {
        Gate::authorize('update', $block);
        abort_unless($block->page_id === $page->id, 404);

        $block->update(['is_visible' => ! $block->is_visible]);

        return back()->with('status', __($block->is_visible ? 'admin.block_shown' : 'admin.block_hidden'));
    }

    public function destroy(Page $page, PageBlock $block): RedirectResponse
    {
        Gate::authorize('delete', $block);
        abort_unless($block->page_id === $page->id, 404);

        DB::transaction(function () use ($page, $block) {
            $block->delete();

            $page->blocks()->orderBy('position')->get()
                ->each(fn (PageBlock $record, int $position) => $record->update(['position' => $position]));
        });

        return redirect()
            ->route('admin.pages.edit', ['locale' => app()->getLocale(), 'page' => $page->slug])
            ->with('status', __('admin.block_deleted'));
    }

    /**
     * Drop the empty rows a repeating field leaves behind.
     *
     * The form always renders one spare row so there is somewhere to type.
     * Saving that spare as an empty item would grow the array by one on
     * every save.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function clean(array $settings): array
    {
        foreach ($settings as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            $filtered = array_values(array_filter($value, function ($item) {
                if (is_array($item)) {
                    return array_filter($item, fn ($field) => $field !== null && trim((string) $field) !== '') !== [];
                }

                return $item !== null && trim((string) $item) !== '';
            }));

            // A fixed group keeps its keys; a repeater is renumbered.
            $settings[$key] = array_is_list($value) ? $filtered : array_filter(
                $value,
                fn ($field) => $field !== null && trim((string) $field) !== ''
            );
        }

        return $settings;
    }
}
