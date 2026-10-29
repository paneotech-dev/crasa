<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BlockType;
use App\Enums\PageStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageBlock;
use App\Services\PageVersioning;
use App\Support\Locales;
use App\Support\PageAddress;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * New page (F9). A Standard page with a title in each language, an address
 * the author may change, and an optional place in the header or footer
 * menu. It is saved as a Draft; it reaches the site through the approval
 * flow. A menu item that points at a page not yet published is left out of
 * the menu by MenuItem, so placing it now cannot show a dead link.
 */
class NewPageController extends Controller
{
    public function __construct(private readonly PageVersioning $versioning) {}

    public function create(): View
    {
        Gate::authorize('create', Page::class);

        return view('pages.admin.pages.create', [
            'locales' => Locales::SUPPORTED,
            'menus' => MenuItem::MENUS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Page::class);

        $request->merge(['slug' => trim(mb_strtolower((string) $request->input('slug')))]);
        if ($request->input('slug') === '') {
            $request->merge(['slug' => PageAddress::suggest((string) $request->input('en.title'))]);
        }

        $data = $request->validate([
            'en.title' => ['required', 'string', 'max:255'],
            'fr.title' => ['nullable', 'string', 'max:255'],
            'pt.title' => ['nullable', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('pages', 'slug'),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (PageAddress::isReserved((string) $value)) {
                        $fail(__('fa.page_slug_reserved', ['slug' => $value]));
                    }
                },
            ],
            'menu' => ['nullable', Rule::in(MenuItem::MENUS)],
        ], [
            'en.title.required' => __('fa.page_title_required'),
            'slug.regex' => __('fa.page_slug_format'),
            'slug.unique' => __('fa.page_slug_taken'),
        ]);

        $page = DB::transaction(function () use ($data, $request) {
            $page = Page::create([
                'slug' => $data['slug'],
                'template' => 'standard',
                'status' => PageStatus::Draft,
                'is_sample' => false,
            ]);

            foreach (Locales::SUPPORTED as $locale) {
                $title = trim((string) ($data[$locale]['title'] ?? ''));
                if ($title !== '') {
                    $page->setTranslations($locale, ['title' => $title], $request->user()->id);
                }
            }

            // One text block to start from, edited with the existing block editor.
            PageBlock::create([
                'page_id' => $page->id,
                'block_type' => BlockType::RichText,
                'position' => 0,
                'is_visible' => true,
                'settings' => BlockType::RichText->defaultSettings(),
            ]);

            if (! empty($data['menu'])) {
                $item = MenuItem::create([
                    'menu' => $data['menu'],
                    'parent_id' => null,
                    'destination_type' => 'page',
                    'destination' => $page->slug,
                    'is_visible' => true,
                    'is_sample' => false,
                    'position' => (int) MenuItem::inMenu($data['menu'])->whereNull('parent_id')->max('position') + 1,
                ]);
                foreach (Locales::SUPPORTED as $locale) {
                    $label = trim((string) ($data[$locale]['title'] ?? ''));
                    if ($label !== '') {
                        $item->setTranslation('label', $locale, $label, $request->user()->id);
                    }
                }
            }

            AuditLog::record(AuditLog::CREATED, $page, ['slug' => $page->slug, 'template' => 'standard', 'menu' => $data['menu'] ?? null], $request->user());

            return $page;
        });

        $this->versioning->record($page, __('fa.page_created_version'), $request->user()->id);

        return redirect()
            ->route('admin.pages.edit', ['locale' => app()->getLocale(), 'page' => $page->slug])
            ->with('status', __('fa.page_created'));
    }
}
