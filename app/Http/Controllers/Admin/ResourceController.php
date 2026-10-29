<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Resource;
use App\Admin\ResourceRegistry;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Locales;
use App\Support\NewsPhoto;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * List, create, edit, delete and restore, for every administered model.
 *
 * Screen 09: "The repeating resource table. Every content type in the back
 * office uses this pattern." So it is one controller against one
 * descriptor, not fifteen controllers that start identical and drift.
 *
 * AUTHORISATION IS PER MODEL, NOT PER CONTROLLER. Every action asks the
 * model's own policy, so a model with no policy is refused rather than
 * waved through. That is the opposite of the usual default and it is the
 * right way round: a new model added to the registry without a policy
 * should be unreachable, not open.
 */
class ResourceController extends Controller
{
    /**
     * The section index: every administered model, with a count.
     *
     * A resource the signed-in account cannot open is not listed. As
     * everywhere else in the back office that is a convenience: each row
     * below re-checks its own policy.
     */
    public function hub(): View
    {
        Gate::authorize('enter-back-office');

        $groups = ['reference' => [], 'content' => []];

        foreach (ResourceRegistry::all() as $definition) {
            $policy = Gate::getPolicyFor($definition->model);

            if ($policy === null || Gate::denies('viewAny', $definition->model)) {
                continue;
            }

            $groups[$definition->isReferenceData ? 'reference' : 'content'][] = [
                'definition' => $definition,
                'count' => $definition->query()->count(),
            ];
        }

        return view('pages.admin.resources.hub', ['groups' => $groups]);
    }

    public function index(Request $request, string $resource): View
    {
        $definition = $this->definition($resource);
        $this->authorizeAction('viewAny', $definition);

        $term = trim($request->string('q')->toString());
        $showingDeleted = $definition->softDeletes() && $request->boolean('deleted');

        $query = $definition->query()
            ->with($definition->with)
            ->when($definition->translatable !== [], fn ($q) => $q->with('translations'))
            ->when($showingDeleted, fn ($q) => $q->onlyTrashed())
            ->when($term !== '' && $definition->searchable !== [], function ($q) use ($definition, $term) {
                $q->where(function ($inner) use ($definition, $term) {
                    foreach ($definition->searchable as $column) {
                        $inner->orWhere($column, 'like', '%'.$term.'%');
                    }

                    if ($definition->translatable !== []) {
                        $inner->orWhereHas('translations', fn ($t) => $t
                            ->whereIn('field', $definition->translatable)
                            ->where('value', 'like', '%'.$term.'%'));
                    }
                });
            });

        $filters = $this->filters($definition, $request);
        foreach ($filters as $filter) {
            if ($filter['value'] === null) {
                continue;
            }
            if ($filter['type'] === 'year') {
                $query->whereYear($filter['column'], (int) $filter['value']);
            } else {
                $query->where($filter['column'], $filter['value']);
            }
        }

        // Undated items (the legacy import's "date not known") after the
        // dated ones: their stored date is the import day, not a real one.
        if ($definition->orderBy !== null && in_array('date_unknown', (new ($definition->model))->getFillable(), true)) {
            $query->orderBy('date_unknown');
        }

        if ($definition->orderBy !== null) {
            $query->orderBy($definition->orderBy, $definition->orderDirection)->orderBy($query->getModel()->getQualifiedKeyName(), $definition->orderDirection);
        }

        return view('pages.admin.resources.index', [
            'definition' => $definition,
            'filters' => $filters,
            'records' => $query->paginate(25)->withQueryString(),
            'showingDeleted' => $showingDeleted,
            'deletedCount' => $definition->softDeletes() ? $definition->query()->onlyTrashed()->count() : 0,
        ]);
    }

    public function create(string $resource): View
    {
        $definition = $this->definition($resource);
        $this->authorizeAction('create', $definition);

        abort_unless($definition->creatable, 403);

        return view('pages.admin.resources.form', [
            'definition' => $definition,
            'record' => $blank = new $definition->model,
            'options' => $this->relationOptions($definition, $blank),
            'locales' => Locales::SUPPORTED,
        ]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        $definition = $this->definition($resource);
        $this->authorizeAction('create', $definition);

        abort_unless($definition->creatable, 403);

        $data = $request->validate($this->rules($definition, new $definition->model, $request));

        $record = new $definition->model;
        $data = NewsPhoto::apply($definition, $record, $request, $data);
        $record->fill($this->attributes($definition, $data));
        $wentLive = $this->guardPublishing($record);
        $this->assignSlug($definition, $record, $data);
        $record->save();

        if ($wentLive) {
            \App\Support\Approvals::recordDirectPublish($record, $request->user());
        }

        $this->writeTranslations($definition, $record, $data, $request->user()->id);

        return redirect()
            ->route('admin.resources.edit', [
                'locale' => app()->getLocale(), 'resource' => $definition->key, 'id' => $record->getKey(),
            ])
            ->with('status', __('admin.resource_created', ['name' => $definition->singular()]));
    }

    public function edit(string $resource, int $id): View
    {
        $definition = $this->definition($resource);
        $record = $this->record($definition, $id);
        $this->authorizeAction('view', $definition, $record);

        return view('pages.admin.resources.form', [
            'definition' => $definition,
            'record' => $record,
            'options' => $this->relationOptions($definition, $record),
            'locales' => Locales::SUPPORTED,
        ]);
    }

    public function update(Request $request, string $resource, int $id): RedirectResponse
    {
        $definition = $this->definition($resource);
        $record = $this->record($definition, $id);
        \App\Support\Approvals::refuseIfLocked($record);
        $this->authorizeAction('update', $definition, $record);

        $data = $request->validate($this->rules($definition, $record, $request));
        $data = NewsPhoto::apply($definition, $record, $request, $data);

        $record->fill($this->attributes($definition, $data));
        $wentLive = $this->guardPublishing($record);
        $record->save();

        if ($wentLive) {
            \App\Support\Approvals::recordDirectPublish($record, $request->user());
        }

        $this->writeTranslations($definition, $record, $data, $request->user()->id);

        return redirect()
            ->route('admin.resources.edit', [
                'locale' => app()->getLocale(), 'resource' => $definition->key, 'id' => $record->getKey(),
            ])
            ->with('status', __('admin.resource_saved', ['name' => $definition->singular()]));
    }

    /**
     * Deleting.
     *
     * Reference data is never deletable, whatever the request says: the
     * flag on the descriptor is checked here rather than only hidden in the
     * view. Everything else soft deletes where the model supports it, and
     * the row is recoverable from the deleted list.
     */
    public function destroy(Request $request, string $resource, int $id): RedirectResponse
    {
        $definition = $this->definition($resource);
        $record = $this->record($definition, $id);
        $this->authorizeAction('delete', $definition, $record);

        if (! $definition->deletable) {
            throw ValidationException::withMessages([
                'record' => __('admin.resource_not_deletable', ['name' => $definition->singular()]),
            ]);
        }

        // Day 3: refused while anything still points at it, naming what.
        if (($refusal = $definition->refusalFor($record)) !== null) {
            throw ValidationException::withMessages(['record' => $refusal]);
        }

        /*
         * A model with no soft deletes is destroyed for good, so the
         * confirmation has to have been typed rather than clicked past.
         * The view asks for the record's own name.
         */
        if (! $definition->softDeletes()) {
            $typed = trim($request->string('confirm')->toString());

            if ($typed !== $definition->titleFor($record)) {
                throw ValidationException::withMessages([
                    'confirm' => __('admin.resource_confirm_mismatch', ['name' => $definition->titleFor($record)]),
                ]);
            }
        }

        $record->delete();

        return redirect()
            ->route('admin.resources', ['locale' => app()->getLocale(), 'resource' => $definition->key])
            ->with('status', __(
                $definition->softDeletes() ? 'admin.resource_deleted_recoverable' : 'admin.resource_deleted',
                ['name' => $definition->singular()]
            ));
    }

    public function restore(string $resource, int $id): RedirectResponse
    {
        $definition = $this->definition($resource);

        abort_unless($definition->softDeletes(), 404);

        $record = $definition->query()->onlyTrashed()->findOrFail($id);
        $this->authorizeAction('restore', $definition, $record);

        $record->restore();

        /*
         * The audit trail records the restore explicitly. The Auditable
         * trait sees a restore as an update of deleted_at, which is true
         * and useless: "somebody set a column to null" is not what an
         * auditor needs to read six months later.
         */
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => AuditLog::UPDATED,
            'auditable_type' => $definition->model,
            'auditable_id' => $record->getKey(),
            'changes' => ['restored' => true],
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return redirect()
            ->route('admin.resources.edit', [
                'locale' => app()->getLocale(), 'resource' => $definition->key, 'id' => $record->getKey(),
            ])
            ->with('status', __('admin.resource_restored', ['name' => $definition->singular()]));
    }

    /* ---------------------------------------------------------------------- */

    /**
     * The list filters a resource declares, with their options and the value
     * asked for. A value that is not one of the options is ignored rather
     * than passed to the query.
     *
     * @return list<array{key: string, column: string, type: string, options: array<string, string>, value: ?string}>
     */
    private function filters(Resource $definition, Request $request): array
    {
        $out = [];

        foreach ($definition->filters as $filter) {
            $type = $filter['type'] ?? 'options';

            if ($type === 'year') {
                $options = $definition->query()
                    ->whereNotNull($filter['column'])
                    ->pluck($filter['column'])
                    ->map(fn ($date) => \Illuminate\Support\Carbon::parse($date)->format('Y'))
                    ->unique()
                    ->sortDesc()
                    ->mapWithKeys(fn ($year) => [$year => $year])
                    ->all();
            } else {
                // A closure lists names already in the interface language
                // (the taxonomy lists); otherwise the options are lang keys.
                $options = $filter['options'] instanceof \Closure
                    ? ($filter['options'])()
                    : collect($filter['options'])->map(fn ($key) => __($key))->all();
            }

            $asked = $request->string($filter['key'])->toString();

            $out[] = [
                'key' => $filter['key'],
                'column' => $filter['column'],
                'type' => $type,
                'options' => $options,
                'value' => array_key_exists($asked, $options) ? $asked : null,
            ];
        }

        return $out;
    }

    /**
     * F2. The generic editor saved news status and publication visibility
     * without asking the publish policy (ROLES_AND_WORKFLOW finding 2).
     * Making a moderated item public here now needs the publish ability,
     * which the approval rules decide; everyone else is told to send it for
     * approval. Returns whether this save puts the item live.
     */
    private function guardPublishing(Model $record): bool
    {
        if (\App\Support\Approvals::typeOf($record) === null) {
            return false;
        }

        $wasPublic = $record->exists && \App\Support\Approvals::isPublic(
            (clone $record)->setRawAttributes($record->getRawOriginal())
        );
        $willBePublic = \App\Support\Approvals::isPublic($record);

        if ($wasPublic || ! $willBePublic) {
            return false;
        }

        if (Gate::denies('publish', $record)) {
            $field = $record instanceof \App\Models\Publication ? 'is_public' : 'status';

            throw ValidationException::withMessages([$field => __('fa.publish_needs_approval')]);
        }

        return true;
    }

    private function definition(string $resource): Resource
    {
        abort_unless(ResourceRegistry::has($resource), 404);

        return ResourceRegistry::find($resource);
    }

    private function record(Resource $definition, int $id): Model
    {
        $query = $definition->query()->with($definition->with);

        if ($definition->translatable !== []) {
            $query->with('translations');
        }

        return $query->findOrFail($id);
    }

    /**
     * Ask the model's own policy.
     *
     * A model with no policy registered is refused, not allowed. Adding a
     * resource to the registry and forgetting its policy should make the
     * screen unreachable rather than open to anyone who can reach the back
     * office.
     */
    private function authorizeAction(string $ability, Resource $definition, ?Model $record = null): void
    {
        /*
         * The door first. Several of these policies answer "may this
         * account read published content", which a viewer holds and which
         * is the right answer for the public site and the wrong one for the
         * back office. The door gate is what separates the two.
         */
        Gate::authorize('enter-back-office');

        $policy = Gate::getPolicyFor($definition->model);

        abort_if($policy === null, 403, 'No policy is registered for '.$definition->model);

        Gate::authorize($ability, $record ?? $definition->model);
    }

    /** @return array<string, mixed> */
    private function rules(Resource $definition, ?Model $record = null, ?Request $request = null): array
    {
        $rules = $definition->rules;

        if ($definition->rulesUsing !== null && $request !== null) {
            $rules = array_merge($rules, ($definition->rulesUsing)($record, $request));
        }

        foreach (Locales::SUPPORTED as $locale) {
            foreach ($definition->translatable as $field) {
                $rules[$locale.'.'.$field] = ['nullable', 'string', 'max:20000'];
            }

            if ($locale !== Locales::FALLBACK) {
                foreach ($definition->localised as $field) {
                    $rules['localised.'.$locale.'.'.$field] = ['nullable', 'string', 'max:2000'];
                }
            }
        }

        $rules['confirm'] = ['nullable', 'string', 'max:255'];

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(Resource $definition, array $data): array
    {
        $attributes = [];

        foreach ($definition->fields as $field) {
            // Shown, never written. See Field::readOnly.
            if ($field->readOnly) {
                continue;
            }

            // The photograph also carries its focal point (Day 3, taxo).
            if ($field->control === 'photo') {
                foreach ($field->options as $column) {
                    if (array_key_exists($column, $data)) {
                        $attributes[$column] = $data[$column];
                    }
                }
            }

            if ($field->control === 'checkbox') {
                $attributes[$field->name] = (bool) ($data[$field->name] ?? false);

                continue;
            }

            if (array_key_exists($field->name, $data)) {
                $attributes[$field->name] = $data[$field->name];
            }
        }

        return $attributes;
    }

    /**
     * A slug for a new record, where the model has one and it is not set.
     *
     * Slugs are permanent on several of these models -- a publication's
     * slug is its citation permalink -- so this only ever runs on create.
     *
     * @param  array<string, mixed>  $data
     */
    private function assignSlug(Resource $definition, Model $record, array $data): void
    {
        if (! in_array('slug', $record->getFillable(), true) || filled($record->slug)) {
            return;
        }

        // A name first where the model has one: a committee's first
        // translatable field is its mandate, a paragraph rather than a name.
        $base = Str::limit(Str::slug(
            $data['name'] ?? $data[Locales::FALLBACK][$definition->translatable[0] ?? ''] ?? $data['reference_number'] ?? 'record'
        ), 70, '') ?: 'record';

        $slug = $base;
        $suffix = 2;

        while ($definition->query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        $record->slug = $slug;
    }

    /** @param array<string, mixed> $data */
    /**
     * Per-language text, and the audit row it would otherwise not get.
     *
     * Auditable watches the model's own attributes, so a save that changes
     * only a translation is a save with nothing dirty and writes nothing to
     * the log. That was invisible while every translatable record also
     * carried a column somebody usually touched at the same time. It stopped
     * being invisible when a committee's terms of reference became
     * translatable on 10 September 2026: rewriting them changed no column at
     * all, and "who changed this committee's mandate" had no answer.
     *
     * The row names the fields and languages that moved, not their contents.
     * The contents are in the translations table, which is where a diff
     * belongs; an audit log carrying every version of every paragraph is an
     * audit log nobody can read.
     */
    private function writeTranslations(Resource $definition, Model $record, array $data, int $by): void
    {
        $changed = [];

        /*
         * Day 3. French and Portuguese for an English column (a member
         * state's name, an indicator's definition). Kept beside the other
         * translations and audited with them.
         */
        if ($definition->localised !== [] && method_exists($record, 'setLocalisedName')) {
            foreach (Locales::SUPPORTED as $locale) {
                if ($locale === Locales::FALLBACK) {
                    continue;
                }

                foreach ($definition->localised as $field) {
                    // Not posted is not cleared: a save from elsewhere that
                    // carries only the columns leaves these alone.
                    if (! \Illuminate\Support\Arr::has($data, 'localised.'.$locale.'.'.$field)) {
                        continue;
                    }

                    $before = $record->storedLocalisedName($field, $locale);
                    $after = $data['localised'][$locale][$field] ?? null;

                    $record->setLocalisedName($field, $locale, $after, $by);

                    if (trim((string) $before) !== trim((string) $record->storedLocalisedName($field, $locale))) {
                        $changed[] = $field.'.'.$locale;
                    }
                }
            }
        }

        foreach (Locales::SUPPORTED as $locale) {
            foreach ($definition->translatable as $field) {
                $before = $record->translationValuesFor($field)[$locale] ?? null;
                $after = $data[$locale][$field] ?? null;

                $record->setTranslation($field, $locale, $after, $by);

                if (trim((string) $before) !== trim((string) $after)) {
                    $changed[] = $field.'.'.$locale;
                }
            }

            $record->unsetRelation('translations');
        }

        if ($changed === [] || $record->wasChanged()) {
            return;
        }

        AuditLog::record($record->wasRecentlyCreated ? AuditLog::CREATED : AuditLog::UPDATED, $record, [
            'translations' => $changed,
        ], $by);
    }

    /**
     * The choices for every relation field, loaded once per form rather
     * than once per row.
     *
     * @return array<string, array<int, string>>
     */
    private function relationOptions(Resource $definition, ?Model $record = null): array
    {
        $options = [];

        foreach ($definition->fields as $field) {
            /*
             * A field whose candidates depend on the record being edited
             * answers for itself. Supersession is the case: a publication
             * may only be superseded by one of the same type with an
             * earlier date, and never by itself, and no generic listing of
             * the related table can know that.
             */
            if ($field->optionsUsing !== null) {
                $options[$field->name] = ($field->optionsUsing)($record);

                continue;
            }

            if ($field->control !== 'relation' || $field->relation === null) {
                continue;
            }

            $related = (new $definition->model)->{$field->relation}()->getRelated();

            $options[$field->name] = $related::query()
                ->orderBy($related->getAttribute('name') !== null || in_array('name', $related->getFillable(), true) ? 'name' : $related->getKeyName())
                ->get()
                ->mapWithKeys(fn (Model $row) => [
                    $row->getKey() => $row->getAttribute('name')
                        ?? $row->getAttribute('title')
                        ?? $row->getAttribute('slug')
                        ?? ('#'.$row->getKey()),
                ])
                ->all();
        }

        return $options;
    }
}
