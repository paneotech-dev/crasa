<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Committee;
use App\Models\MediaAsset;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Publication;
use App\Models\Approval;
use App\Models\Translation;
use App\Services\MachineTranslation\MachineDrafts;
use App\Support\Approvals;
use App\Support\Locales;
use App\Support\TranslationCoverage;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Translation management, CM-04, against screen 05.
 *
 * Item 1b (Day 3): approving an item now drafts its other languages by
 * machine (App\Services\MachineTranslation\MachineDrafts). Those drafts are
 * listed at the top of this screen for review, each flagged as a machine
 * draft until an editor approves or rewrites it.
 *
 * MACHINE DRAFTS ARE NEVER CREATED HERE. Screen 05 draws the state because
 * the client may import copy that came from a machine, and the editor has
 * to be able to see which fields those are and approve or rewrite them.
 * Nothing in this platform sets is_machine_draft: no field is sent to a
 * translation service, and no English string is copied into a French or
 * Portuguese row to make a coverage bar look better. Screen 05 says it
 * plainly and it is the rule: "Machine drafts are never published as
 * approved copy for this client."
 *
 * One import, on the platform owner's instruction (14 October 2026): the
 * French and Portuguese for every text that had only English, and English
 * summaries for 29 news items that had none, written by machine. Every one
 * of those rows has is_machine_draft set, so this screen shows each as a
 * machine draft until an editor approves or rewrites it, and the coverage
 * figure does not count them. docs/TRANSLATION_REVIEW.md lists them.
 *
 * The coverage figure counts approved fields only, so a language that is
 * entirely machine-drafted reads as not ready rather than as finished.
 */
class TranslationController extends Controller
{
    /**
     * The record types that carry translations and are worth editing here.
     *
     * @var array<string, class-string<Model>>
     */
    private const TYPES = [
        'pages' => Page::class,
        'blocks' => PageBlock::class,
        'publications' => Publication::class,
        'news' => NewsItem::class,
        'media' => MediaAsset::class,
        // Terms of reference, translatable since 9 September 2026.
        'committees' => Committee::class,
    ];

    public function index(Request $request): View
    {
        Gate::authorize(Permission::EditTranslations->value);

        $type = $request->string('type')->toString();
        $type = array_key_exists($type, self::TYPES) ? $type : 'pages';
        $class = self::TYPES[$type];

        $records = $class::query()
            ->with('translations')
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        $rows = [];

        foreach ($records as $record) {
            $fields = $this->fieldsFor($record);
            $coverage = TranslationCoverage::for($record, $fields);

            $rows[] = [
                'record' => $record,
                'label' => $this->labelFor($record),
                'coverage' => $coverage,
                'ready' => collect($coverage)->every(fn (array $c) => $c['ready']),
            ];
        }

        return view('pages.admin.translations.index', [
            'approvedDrafts' => $this->approvedDrafts(),
            'machineConfigured' => MachineDrafts::configured(),
            'records' => $records,
            'rows' => $rows,
            'type' => $type,
            'types' => array_keys(self::TYPES),
        ]);
    }

    public function edit(Request $request, string $type, int $id): View
    {
        Gate::authorize(Permission::EditTranslations->value);

        abort_unless(array_key_exists($type, self::TYPES), 404);

        $record = self::TYPES[$type]::with('translations')->findOrFail($id);
        $fields = $this->fieldsFor($record);

        /*
         * Three views, as screen 05 draws them: all three languages
         * side by side, or English against one other. The pair views exist
         * because three columns of a long body field on a laptop leave
         * nobody enough width to read either language properly.
         */
        $pair = $request->string('pair')->toString();
        $columns = in_array($pair, Locales::SUPPORTED, true) && $pair !== Locales::FALLBACK
            ? [Locales::FALLBACK, $pair]
            : Locales::SUPPORTED;

        return view('pages.admin.translations.edit', [
            'record' => $record,
            'type' => $type,
            'label' => $this->labelFor($record),
            'fields' => $fields,
            'columns' => $columns,
            'pair' => $pair,
            'coverage' => TranslationCoverage::for($record, $fields),
        ]);
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        Gate::authorize(Permission::EditTranslations->value);

        abort_unless(array_key_exists($type, self::TYPES), 404);

        $record = self::TYPES[$type]::with('translations')->findOrFail($id);
        $fields = $this->fieldsFor($record);

        $rules = [];

        foreach (Locales::SUPPORTED as $locale) {
            foreach ($fields as $field) {
                $rules[$locale.'.'.$field] = ['nullable', 'string', 'max:20000'];
            }
        }

        $data = $request->validate($rules);

        foreach (Locales::SUPPORTED as $locale) {
            foreach ($fields as $field) {
                $value = $data[$locale][$field] ?? null;

                /*
                 * Writing a field by hand clears the machine-draft flag on
                 * it. That is the "Rewrite" action in screen 05: a person
                 * has now written this string, so it is no longer a draft
                 * that came from a machine.
                 */
                $record->setTranslation($field, $locale, $value, $request->user()->id);
            }
        }

        return redirect()
            ->route('admin.translations.edit', [
                'locale' => app()->getLocale(), 'type' => $type, 'id' => $id,
                'pair' => $request->string('pair')->toString() ?: null,
            ])
            ->with('status', __('admin.translations_saved'));
    }

    /**
     * Approve a machine draft as it stands.
     *
     * Screen 05's other action. It records that a speaker of the language
     * has read the string and stands behind it, which is the only thing
     * that turns a draft into copy. Nothing is rewritten.
     */
    public function approve(Request $request, string $type, int $id): RedirectResponse
    {
        Gate::authorize(Permission::EditTranslations->value);

        abort_unless(array_key_exists($type, self::TYPES), 404);

        $record = self::TYPES[$type]::with('translations')->findOrFail($id);

        $data = $request->validate([
            'field' => ['required', 'string', 'max:60'],
            'approve_locale' => ['required', 'in:'.implode(',', Locales::SUPPORTED)],
        ]);

        $translation = $record->translations->firstWhere(
            fn ($t) => $t->locale === $data['approve_locale'] && $t->field === $data['field']
        );

        abort_if($translation === null, 404);

        $translation->update([
            'is_machine_draft' => false,
            'translated_by' => $request->user()->id,
        ]);

        return back()->with('status', __('admin.translation_approved'));
    }

    /**
     * The public page, rendered in one language, so an editor sees what a
     * reader sees.
     *
     * Screen 05: "The summary has no Portuguese translation, so the English
     * text is shown, marked as English with a lang attribute so a screen
     * reader switches voice, and preceded by a plain notice. It is never
     * left blank, and never machine translated on the fly." This is that
     * preview, and it is the real renderer rather than a mock-up of it.
     */
    public function preview(string $type, int $id, string $previewLocale): View
    {
        Gate::authorize(Permission::EditTranslations->value);

        abort_unless(array_key_exists($type, self::TYPES), 404);
        abort_unless(in_array($previewLocale, Locales::SUPPORTED, true), 404);

        $record = self::TYPES[$type]::with('translations')->findOrFail($id);
        $fields = $this->fieldsFor($record);

        app()->setLocale($previewLocale);

        $resolved = [];

        foreach ($fields as $field) {
            $resolved[$field] = $record->translate($field, $previewLocale);
        }

        return view('pages.admin.translations.preview', [
            'record' => $record,
            'type' => $type,
            'label' => $this->labelFor($record),
            'previewLocale' => $previewLocale,
            'fields' => $fields,
            'resolved' => $resolved,
        ]);
    }

    /**
     * Item 1b: approved items that still carry machine drafts, newest first,
     * with the languages to review and a link to the item itself.
     *
     * @return list<array{type: string, record: Model, label: string, locales: list<string>, item_url: string}>
     */
    private function approvedDrafts(): array
    {
        $rows = Translation::query()
            ->where('is_machine_draft', '=', true)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('approvals')
                ->whereColumn('approvals.approvable_type', 'translations.translatable_type')
                ->whereColumn('approvals.approvable_id', 'translations.translatable_id')
                ->where('approvals.state', Approval::APPROVED))
            ->orderByDesc('updated_at')
            ->get(['translatable_type', 'translatable_id', 'locale'])
            ->groupBy(fn (Translation $t) => $t->translatable_type.'#'.$t->translatable_id)
            ->take(50);

        $out = [];

        foreach ($rows as $group) {
            $first = $group->first();
            $class = \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($first->translatable_type) ?? $first->translatable_type;
            $type = array_search($class, self::TYPES, true);
            $record = $type === false ? null : $class::with('translations')->find($first->translatable_id);

            if ($record === null) {
                continue;
            }

            $out[] = [
                'type' => $type,
                'record' => $record,
                'label' => $this->labelFor($record),
                'locales' => $group->pluck('locale')->unique()->sort()->values()->all(),
                'item_url' => Approvals::adminUrl($record),
            ];
        }

        return $out;
    }

    /** @return list<string> */
    private function fieldsFor(Model $record): array
    {
        if ($record instanceof PageBlock) {
            return $record->type()->translatableFields();
        }

        // Every other type declares its own list on the model.
        $reflection = new \ReflectionProperty($record, 'translatable');

        return $reflection->getValue($record);
    }

    private function labelFor(Model $record): string
    {
        if ($record instanceof PageBlock) {
            return ($record->page?->slug ?? '').' — '.$record->type()->label();
        }

        if ($record instanceof MediaAsset) {
            return $record->original_name;
        }

        return $record->translate('title')?->value ?? ($record->slug ?? (string) $record->getKey());
    }
}
