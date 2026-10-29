<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Sector;
use App\Models\Translation;
use App\Support\BadgeColours;
use App\Support\Locales;
use App\Support\PublicCache;
use App\Support\Taxonomies;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Content > Taxonomies: news categories, publication types, sectors and
 * tags. List, new, edit, delete.
 *
 * WHO. The roles grid has no row for the structure of the content, only
 * for the content itself (ManageContent, which editors hold). A category
 * renamed here renames a filter on every public page in three languages,
 * which is a site setting rather than an article, so the screen asks for
 * ManageSettings: administrators, as the grid ships. A role given that
 * permission on the roles screen gets this screen with it.
 *
 * DELETE IS REFUSED WHILE IN USE, with the number of records using the
 * term. Not reassigned silently: moving twenty-five news items to another
 * category is an editorial decision, and the person deleting should make
 * it knowingly.
 */
class TaxonomyController extends Controller
{
    public function index(Request $request, string $kind = 'categories'): View
    {
        $this->authorizeScreen();
        abort_unless(Taxonomies::has($kind), 404);

        $terms = Taxonomies::list($kind);

        return view('pages.admin.taxonomies.index', [
            'kind' => $kind,
            'kinds' => array_keys(Taxonomies::KINDS),
            'terms' => $terms,
            'usage' => $terms->mapWithKeys(fn (Model $term) => [$term->getKey() => Taxonomies::usage($kind, $term)])->all(),
        ]);
    }

    public function create(string $kind): View
    {
        $this->authorizeScreen();
        abort_unless(Taxonomies::has($kind), 404);

        $model = Taxonomies::model($kind);
        $term = new $model;
        $term->sort_order = (int) $model::query()->max('sort_order') + 1;

        return $this->form($kind, $term);
    }

    public function store(Request $request, string $kind): RedirectResponse
    {
        $this->authorizeScreen();
        abort_unless(Taxonomies::has($kind), 404);

        $model = Taxonomies::model($kind);
        $term = new $model;
        $data = $request->validate($this->rules($kind, $term));

        DB::transaction(fn () => $this->save($kind, $term, $data, $request->user()->id));

        return redirect()
            ->route('admin.taxonomies', ['kind' => $kind])
            ->with('status', __('d3_taxo.created', ['name' => $data['name']['en']]));
    }

    public function edit(string $kind, int $id): View
    {
        $this->authorizeScreen();

        return $this->form($kind, $this->term($kind, $id));
    }

    public function update(Request $request, string $kind, int $id): RedirectResponse
    {
        $this->authorizeScreen();
        $term = $this->term($kind, $id);
        $data = $request->validate($this->rules($kind, $term));

        DB::transaction(fn () => $this->save($kind, $term, $data, $request->user()->id));

        return redirect()
            ->route('admin.taxonomies', ['kind' => $kind])
            ->with('status', __('d3_taxo.saved', ['name' => $data['name']['en']]));
    }

    public function destroy(string $kind, int $id): RedirectResponse
    {
        $this->authorizeScreen();
        $term = $this->term($kind, $id);
        $name = Taxonomies::label($kind, $term);
        $used = Taxonomies::usage($kind, $term);

        if ($used > 0) {
            throw ValidationException::withMessages([
                'term' => trans_choice('d3_taxo.in_use', $used, ['name' => $name, 'count' => $used]),
            ]);
        }

        DB::transaction(function () use ($term) {
            // Translations are polymorphic and do not cascade. Sectors do
            // not use HasTranslations, so their names are removed here.
            Translation::query()
                ->where('translatable_type', $term->getMorphClass())
                ->where('translatable_id', $term->getKey())
                ->delete();

            $term->delete();
        });

        Sector::forgetNames();
        PublicCache::flush();

        return redirect()
            ->route('admin.taxonomies', ['kind' => $kind])
            ->with('status', __('d3_taxo.deleted', ['name' => $name]));
    }

    /* ---------------------------------------------------------------------- */

    private function authorizeScreen(): void
    {
        Gate::authorize('enter-back-office');
        Gate::authorize(Permission::ManageSettings->value);
    }

    private function term(string $kind, int $id): Model
    {
        abort_unless(Taxonomies::has($kind), 404);

        return Taxonomies::model($kind)::query()->findOrFail($id);
    }

    private function form(string $kind, Model $term): View
    {
        return view('pages.admin.taxonomies.form', [
            'kind' => $kind,
            'term' => $term,
            'names' => Taxonomies::names($kind, $term),
            'usage' => $term->exists ? Taxonomies::usage($kind, $term) : 0,
            'colours' => Taxonomies::hasColour($kind) ? BadgeColours::keys() : [],
            // A sector's slug names its icon, its photograph and the
            // addresses the home page links to, so it is fixed once made.
            'slugLocked' => $kind === 'sectors' && $term->exists,
        ]);
    }

    /** @return array<string, mixed> */
    private function rules(string $kind, Model $term): array
    {
        $max = Taxonomies::KINDS[$kind]['slug_max'];

        $rules = [
            'slug' => [
                'required', 'string', 'max:'.$max, 'regex:/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/',
                Rule::unique($term->getTable(), 'slug')->ignore($term->exists ? $term->getKey() : null),
            ],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ];

        if ($kind === 'sectors' && $term->exists) {
            $rules['slug'] = ['nullable'];
        }

        foreach (Locales::SUPPORTED as $locale) {
            $rules['name.'.$locale] = ['required', 'string', 'max:80'];
        }

        if (Taxonomies::hasColour($kind)) {
            $rules['colour'] = ['required', Rule::in(BadgeColours::keys())];
        }

        return $rules;
    }

    /** @param array<string, mixed> $data */
    private function save(string $kind, Model $term, array $data, int $by): void
    {
        $before = Taxonomies::names($kind, $term);

        if (! ($kind === 'sectors' && $term->exists)) {
            $term->slug = $data['slug'];
        }

        $term->sort_order = (int) $data['sort_order'];

        if (Taxonomies::hasColour($kind)) {
            $term->colour = $data['colour'];
        }

        if ($kind === 'sectors') {
            $term->name = $data['name'][Locales::FALLBACK];

            if (! $term->exists) {
                // Columns the sectors table requires. The icon falls back to
                // the neutral glyph x-sector-icon draws for an unknown slug.
                $term->icon = 'circle';
                $term->accent_token = '--c-navy';
            }
        }

        $term->save();

        $changed = [];

        foreach (Locales::SUPPORTED as $locale) {
            $value = trim((string) $data['name'][$locale]);

            if ($kind === 'sectors' && $locale === Locales::FALLBACK) {
                continue;
            }

            if ($kind === 'sectors') {
                Translation::updateOrCreate(
                    ['translatable_type' => $term->getMorphClass(), 'translatable_id' => $term->getKey(), 'locale' => $locale, 'field' => 'name'],
                    ['value' => $value, 'translated_by' => $by, 'is_machine_draft' => false],
                );
            } else {
                $term->setTranslation('name', $locale, $value, $by);
            }

            if (trim($before[$locale] ?? '') !== $value) {
                $changed[] = 'name.'.$locale;
            }
        }

        // Names live in the translations table, which the model's own
        // audit (Auditable, on all four kinds) does not see.
        if ($changed !== []) {
            AuditLog::record($term->wasRecentlyCreated ? AuditLog::CREATED : AuditLog::UPDATED, $term, [
                'translations' => $changed,
                'slug' => $term->slug,
            ], $by);
        }

        if (method_exists($term, 'forgetTerms')) {
            $term::forgetTerms();
        }
        Sector::forgetNames();
        PublicCache::flush();
    }
}
