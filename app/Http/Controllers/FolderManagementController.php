<?php

namespace App\Http\Controllers;

use App\Enums\Clearance;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Document;
use App\Models\Folder;
use App\Models\User;
use App\Support\Documents\DocumentStore;
use App\Support\Documents\FolderAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Folders: create, rename, describe, move, set who may see, upload and
 * validate, and delete when empty. Three levels at most.
 *
 * The same screens serve the back office (admin.documents.*) and the
 * member area (extranet.manage.*); only the shell around them differs.
 * Every action re-checks the folder policy: the Secretariat (content
 * managers) manage folders, deleting needs the delete permission, and a
 * folder another member state owns is not found at all.
 */
class FolderManagementController extends Controller
{
    /** The back office's view of every folder, as a tree. */
    public function index(Request $request): View
    {
        Gate::authorize('create', Folder::class);

        $all = Folder::query()->with(['country', 'committee'])->withCount('documents')->orderBy('name')->get()
            ->filter(fn (Folder $folder) => Gate::allows('view', $folder));

        return view('pages.documents.admin-index', [
            'rows' => $this->flatten($all),
            'binCount' => Document::onlyTrashed()->whereNotNull('folder_id')->count(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Folder::class);

        $parent = $request->filled('parent')
            ? Folder::findOrFail((int) $request->query('parent'))
            : null;

        if ($parent !== null) {
            Gate::authorize('update', $parent);
        }

        $folder = new Folder(['parent_id' => $parent?->id, 'clearance' => $parent?->clearance ?? Clearance::Member]);
        $folder->country_id = $parent?->country_id;
        $folder->committee_id = $parent?->committee_id;

        return $this->form($request, $folder);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Folder::class);

        $data = $this->validated($request, null);
        $parent = $data['parent'];

        $folder = DB::transaction(function () use ($data, $parent): Folder {
            $folder = Folder::unscoped()->create([
                'parent_id' => $parent?->id,
                'country_id' => $parent?->country_id,
                'committee_id' => $parent?->committee_id,
                'name' => $data['name'],
                'slug' => $this->slug($data['name'], $parent?->id),
                'description' => $data['description'],
                'clearance' => $data['clearance'],
                'access' => $data['access'],
            ]);

            FolderAccess::sync($folder);

            return $folder;
        });

        DocumentStore::record(DocumentStore::FOLDER_CREATED, $folder, $folder, ['name' => $folder->name], $request->user());

        return redirect()
            ->route('extranet.folder', ['locale' => app()->getLocale(), 'folder' => $folder->id])
            ->with('status', __('d3_docs.folder_created', ['name' => $folder->name]));
    }

    public function edit(Request $request, Folder $folder): View
    {
        Gate::authorize('update', $folder);

        return $this->form($request, $folder);
    }

    public function update(Request $request, Folder $folder): RedirectResponse
    {
        Gate::authorize('update', $folder);

        $data = $this->validated($request, $folder);
        $parent = $data['parent'];
        $before = ['name' => $folder->name, 'parent_id' => $folder->parent_id, 'clearance' => $folder->clearance->value, 'access' => $folder->access];

        DB::transaction(function () use ($folder, $data, $parent): void {
            $folder->fill([
                'parent_id' => $parent?->id,
                'name' => $data['name'],
                'description' => $data['description'],
                'clearance' => $data['clearance'],
                'access' => $data['access'],
            ]);

            if ($folder->isDirty('parent_id') || $folder->isDirty('name')) {
                $folder->slug = $this->slug($data['name'], $parent?->id, $folder->id);
            }

            $folder->save();
            FolderAccess::sync($folder);
        });

        DocumentStore::record(DocumentStore::FOLDER_CHANGED, $folder, $folder, ['before' => $before, 'after' => [
            'name' => $folder->name, 'parent_id' => $folder->parent_id, 'clearance' => $folder->clearance->value, 'access' => $folder->access,
        ]], $request->user());

        return redirect()
            ->route('extranet.folder', ['locale' => app()->getLocale(), 'folder' => $folder->id])
            ->with('status', __('d3_docs.folder_saved', ['name' => $folder->name]));
    }

    /** Deleting a folder: only an empty one, with no files and no subfolders. */
    public function destroy(Request $request, Folder $folder): RedirectResponse
    {
        Gate::authorize('delete', $folder);

        $children = Folder::unscoped()->where('parent_id', $folder->id)->count();
        $files = Document::query()->where('folder_id', $folder->id)->count();
        $binned = Document::onlyTrashed()->where('folder_id', $folder->id)->count();

        if ($children > 0 || $files > 0 || $binned > 0) {
            return back()->withErrors(['folder' => match (true) {
                $children > 0 => __('d3_docs.delete_not_empty_folders', ['count' => $children]),
                $files > 0 => __('d3_docs.delete_not_empty_files', ['count' => $files]),
                default => __('d3_docs.delete_not_empty_bin', ['count' => $binned]),
            }]);
        }

        $parentId = $folder->parent_id;
        DocumentStore::record(DocumentStore::FOLDER_DELETED, $folder, $folder, ['name' => $folder->name], $request->user());
        $folder->delete();

        $back = $request->routeIs('admin.*') || $parentId === null
            ? ($request->routeIs('admin.*') ? route('admin.documents', ['locale' => app()->getLocale()]) : route('extranet.folders', ['locale' => app()->getLocale()]))
            : route('extranet.folder', ['locale' => app()->getLocale(), 'folder' => $parentId]);

        return redirect($back)->with('status', __('d3_docs.folder_deleted', ['name' => $folder->name]));
    }

    /* ------------------------------------------------------------------ */

    private function form(Request $request, Folder $folder): View
    {
        $admin = $request->routeIs('admin.*');
        $exclude = $folder->exists ? $folder->subtreeIds() : [];
        $height = $folder->exists ? $folder->height() : 1;

        // Where this folder may go: any folder the reader can manage that
        // is not inside it and leaves room for its levels.
        $parents = $this->flatten(
            Folder::query()->orderBy('name')->get()->filter(fn (Folder $f) => Gate::allows('update', $f))
        )->filter(fn (array $row) => ! in_array($row['folder']->id, $exclude, true) && $row['depth'] + $height <= Folder::MAX_DEPTH)->values();

        $own = is_array($folder->access) && $folder->access !== [];

        return view('pages.documents.folder-form', [
            'layout' => $admin ? 'layouts.admin' : 'layouts.extranet',
            'surface' => $admin ? 'admin' : 'extranet',
            'folder' => $folder,
            'parents' => $parents,
            'inherits' => $folder->parent_id !== null && ! $own,
            'rules' => collect(FolderAccess::ABILITIES)->mapWithKeys(fn (string $a) => [$a => FolderAccess::ruleFor($folder, $a)])->all(),
            'countries' => Country::orderBy('name')->get(),
            'committees' => Committee::orderBy('name')->get(),
            'users' => User::orderBy('name')->get(['id', 'name', 'email', 'country_id']),
            'roles' => FolderAccess::roles(),
        ]);
    }

    /**
     * @return array{name: string, description: ?string, clearance: Clearance, parent: ?Folder, access: ?array<string, mixed>}
     */
    private function validated(Request $request, ?Folder $folder): array
    {
        $types = FolderAccess::TYPES;

        $rules = [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer'],
            'clearance' => ['required', Rule::in([Clearance::Member->value, Clearance::Confidential->value, Clearance::Secretariat->value])],
            'inherit' => ['nullable', 'boolean'],
        ];

        foreach (FolderAccess::ABILITIES as $ability) {
            $allowed = $ability === 'view' ? array_values(array_diff($types, ['secretariat'])) : $types;
            $rules["access.$ability.type"] = ['required_unless:inherit,1', 'nullable', Rule::in($allowed)];
            $rules["access.$ability.roles"] = ['array', "required_if:access.$ability.type,roles"];
            $rules["access.$ability.roles.*"] = [Rule::in(array_map(fn ($r) => $r->value, FolderAccess::roles()))];
            $rules["access.$ability.country_id"] = ["required_if:access.$ability.type,country", 'nullable', 'integer', 'exists:countries,id'];
            $rules["access.$ability.committee_id"] = ["required_if:access.$ability.type,committee", 'nullable', 'integer', 'exists:committees,id'];
            $rules["access.$ability.user_ids"] = ['array', "required_if:access.$ability.type,users"];
            $rules["access.$ability.user_ids.*"] = ['integer', 'exists:users,id'];
        }

        $data = $request->validate($rules, [], [
            'access.view.type' => __('d3_docs.access_view'),
            'access.upload.type' => __('d3_docs.access_upload'),
            'access.validate.type' => __('d3_docs.access_validate'),
        ]);

        $parent = null;

        if (! empty($data['parent_id'])) {
            // Through the scope: a folder the reader cannot see is not a
            // place to put anything.
            $parent = Folder::find((int) $data['parent_id']);

            if ($parent === null || Gate::denies('update', $parent)) {
                throw ValidationException::withMessages(['parent_id' => __('d3_docs.parent_unknown')]);
            }

            if ($folder !== null && in_array($parent->id, $folder->subtreeIds(), true)) {
                throw ValidationException::withMessages(['parent_id' => __('d3_docs.parent_inside')]);
            }
        }

        $height = $folder?->exists ? $folder->height() : 1;
        $depth = $parent ? $parent->depth() : 0;

        if ($depth + $height > Folder::MAX_DEPTH) {
            throw ValidationException::withMessages(['parent_id' => __('d3_docs.too_deep', ['max' => Folder::MAX_DEPTH])]);
        }

        $clearance = Clearance::from($data['clearance']);

        if ($parent !== null && $clearance->rank() < $parent->clearance->rank()) {
            throw ValidationException::withMessages(['clearance' => __('d3_docs.clearance_below_parent', [
                'parent' => $parent->name, 'clearance' => $parent->clearance->label(),
            ])]);
        }

        // The folders below may not end up less restricted than this one.
        if ($folder?->exists) {
            $lowest = Folder::unscoped()->whereIn('id', array_diff($folder->subtreeIds(), [$folder->id]))->get()
                ->first(fn (Folder $child) => $child->clearance->rank() < $clearance->rank());

            if ($lowest !== null) {
                throw ValidationException::withMessages(['clearance' => __('d3_docs.clearance_above_child', ['child' => $lowest->name])]);
            }
        }

        $access = null;

        // A root folder has nothing to inherit from, so it always states
        // its rules.
        if (! ($request->boolean('inherit') && $parent !== null)) {
            $access = [];
            foreach (FolderAccess::ABILITIES as $ability) {
                $access[$ability] = FolderAccess::clean($data['access'][$ability] ?? ['type' => $ability === 'view' ? 'signed_in' : 'secretariat']);
            }
        }

        return [
            'name' => trim($data['name']),
            'description' => isset($data['description']) && trim($data['description']) !== '' ? trim($data['description']) : null,
            'clearance' => $clearance,
            'parent' => $parent,
            'access' => $access,
        ];
    }

    private function slug(string $name, ?int $parentId, ?int $except = null): string
    {
        $base = Str::limit(Str::slug($name) ?: 'folder', 100, '');
        $slug = $base;

        for ($i = 2; $i < 200; $i++) {
            $taken = Folder::withoutGlobalScopes()->withTrashed()
                ->where('parent_id', $parentId)
                ->where('slug', $slug)
                ->when($except, fn ($q) => $q->whereKeyNot($except))
                ->exists();

            if (! $taken) {
                return $slug;
            }

            $slug = $base.'-'.$i;
        }

        return $base.'-'.Str::lower(Str::random(6));
    }

    /**
     * Folders as rows in tree order, each with its depth (1 for a root).
     * A folder whose parent the reader cannot see is shown as a root.
     *
     * @param  Collection<int, Folder>  $folders
     * @return Collection<int, array{folder: Folder, depth: int}>
     */
    private function flatten($folders)
    {
        $byParent = $folders->groupBy(fn (Folder $f) => $f->parent_id ?? 0);
        $ids = $folders->pluck('id')->all();
        $rows = collect();

        $walk = function (Folder $folder, int $depth) use (&$walk, $byParent, $rows): void {
            $rows->push(['folder' => $folder, 'depth' => $depth]);

            foreach ($byParent->get($folder->id, collect()) as $child) {
                if ($depth < 10) {
                    $walk($child, $depth + 1);
                }
            }
        };

        foreach ($folders as $folder) {
            if ($folder->parent_id === null || ! in_array($folder->parent_id, $ids, true)) {
                $walk($folder, $folder->parent_id === null ? 1 : $folder->depth());
            }
        }

        return $rows;
    }
}
