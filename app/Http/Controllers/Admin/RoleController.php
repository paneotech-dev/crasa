<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Grant;
use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\RoleDefinition;
use App\Support\AdminAlerts;
use App\Support\PermissionMatrix;
use App\Support\RoleAreas;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Roles under People (F3): the list with user counts, the permission grid,
 * copy, create, delete when unused. Holders of "assign roles" only.
 *
 * The seven presets are built in: editable, never deletable. The
 * administrator preset always keeps "assign roles" and "manage users", or
 * nobody could undo a mistake made here.
 */
class RoleController extends Controller
{
    private const LOCKED = [Permission::AssignRoles, Permission::ManageUsers];

    public function index(Request $request): View
    {
        $this->gate($request);

        $roles = RoleDefinition::orderByDesc('is_built_in')->orderBy('id')->get();

        return view('pages.admin.roles.index', [
            'roles' => $roles,
            'counts' => $roles->mapWithKeys(fn (RoleDefinition $r) => [$r->id => $r->userCount()]),
        ]);
    }

    public function create(Request $request): View
    {
        $this->gate($request);

        $source = $request->filled('copy') ? RoleDefinition::where('key', $request->string('copy'))->firstOrFail() : null;
        $role = new RoleDefinition([
            'name_en' => $source ? __('fa.roles_copy_of', ['name' => $source->name_en], 'en') : '',
            'name_fr' => $source ? __('fa.roles_copy_of', ['name' => $source->name_fr ?: $source->name_en], 'fr') : '',
            'name_pt' => $source ? __('fa.roles_copy_of', ['name' => $source->name_pt ?: $source->name_en], 'pt') : '',
            'base_role' => $source?->base_role ?? Role::Viewer->value,
        ]);

        return view('pages.admin.roles.form', [
            'role' => $role,
            'grid' => $source ? $this->gridOf($source) : [],
            'source' => $source,
            'areas' => RoleAreas::grid(),
            'baseRoles' => Role::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->gate($request);
        $data = $this->validated($request, null);

        $role = DB::transaction(function () use ($data) {
            $base = Str::slug($data['name_en']) ?: 'role';
            $key = $base;
            $n = 2;
            while (RoleDefinition::where('key', $key)->exists() || Role::tryFrom($key) !== null) {
                $key = $base.'-'.$n++;
            }

            $role = RoleDefinition::create([
                'key' => $key, 'name_en' => $data['name_en'], 'name_fr' => $data['name_fr'] ?? null,
                'name_pt' => $data['name_pt'] ?? null, 'base_role' => $data['base_role'], 'is_built_in' => false,
            ]);
            $this->writeGrid($role, $data['grant'] ?? []);

            return $role;
        });

        AuditLog::record('role_created', $role, ['key' => $role->key, 'grid' => $this->gridOf($role)], $request->user());
        AdminAlerts::send($request->user(), 'role_saved', ['subject' => $role->name_en]);

        return redirect()->route('admin.roles.edit', ['locale' => app()->getLocale(), 'role' => $role->key])
            ->with('status', __('fa.roles_created', ['name' => $role->label()]));
    }

    public function edit(Request $request, string $role): View
    {
        $this->gate($request);
        $definition = RoleDefinition::where('key', $role)->firstOrFail();

        return view('pages.admin.roles.form', [
            'role' => $definition,
            'grid' => $this->gridOf($definition),
            'source' => null,
            'areas' => RoleAreas::grid(),
            'baseRoles' => Role::cases(),
            'count' => $definition->userCount(),
        ]);
    }

    public function update(Request $request, string $role): RedirectResponse
    {
        $this->gate($request);
        $definition = RoleDefinition::where('key', $role)->firstOrFail();
        $data = $this->validated($request, $definition);
        $before = $this->gridOf($definition);

        DB::transaction(function () use ($definition, $data) {
            $definition->update([
                'name_en' => $data['name_en'], 'name_fr' => $data['name_fr'] ?? null, 'name_pt' => $data['name_pt'] ?? null,
                'base_role' => $definition->is_built_in ? $definition->base_role : $data['base_role'],
            ]);
            $this->writeGrid($definition, $data['grant'] ?? []);
        });

        $after = $this->gridOf($definition);
        $diff = [];
        foreach ($after as $permission => $grant) {
            if (($before[$permission] ?? 'denied') !== $grant) {
                $diff[$permission] = ['from' => $before[$permission] ?? 'denied', 'to' => $grant];
            }
        }

        AuditLog::record('role_saved', $definition, ['key' => $definition->key, 'changed' => $diff], $request->user());
        if ($diff !== []) {
            AdminAlerts::send($request->user(), 'role_saved', ['subject' => $definition->name_en]);
        }

        return back()->with('status', __('fa.roles_saved', ['name' => $definition->label()]));
    }

    public function destroy(Request $request, string $role): RedirectResponse
    {
        $this->gate($request);
        $definition = RoleDefinition::where('key', $role)->firstOrFail();

        if ($definition->is_built_in) {
            throw ValidationException::withMessages(['role' => __('fa.roles_built_in')]);
        }

        if (($count = $definition->userCount()) > 0) {
            throw ValidationException::withMessages(['role' => trans_choice('fa.roles_in_use', $count, ['count' => $count])]);
        }

        AuditLog::record('role_deleted', null, ['key' => $definition->key, 'name' => $definition->name_en, 'grid' => $this->gridOf($definition)], $request->user());
        $definition->delete();
        PermissionMatrix::flush();

        return redirect()->route('admin.roles', ['locale' => app()->getLocale()])
            ->with('status', __('fa.roles_deleted', ['name' => $definition->label()]));
    }

    /* ------------------------------------------------------------------ */

    private function gate(Request $request): void
    {
        abort_unless($request->user()->hasPermission(Permission::AssignRoles), 403);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?RoleDefinition $role): array
    {
        $grants = array_column(Grant::cases(), 'value');
        $rules = [
            'name_en' => ['required', 'string', 'max:120'],
            'name_fr' => ['nullable', 'string', 'max:120'],
            'name_pt' => ['nullable', 'string', 'max:120'],
            'base_role' => [$role?->is_built_in ? 'nullable' : 'required', Rule::in(array_column(Role::cases(), 'value'))],
            'grant' => ['array'],
        ];
        foreach (Permission::cases() as $permission) {
            $rules['grant.'.$permission->value] = ['nullable', Rule::in($grants)];
        }

        $data = $request->validate($rules, [
            'name_en.required' => __('fa.roles_name_required'),
        ]);

        // Dots in permission codes arrive as nested arrays from PHP's input
        // parser; flatten them back to codes.
        $data['grant'] = \Illuminate\Support\Arr::dot($request->input('grant', []));

        if ($role?->key === Role::Administrator->value) {
            foreach (self::LOCKED as $permission) {
                if (($data['grant'][$permission->value] ?? 'denied') !== Grant::Granted->value) {
                    throw ValidationException::withMessages(['grant' => __('fa.roles_admin_locked')]);
                }
            }
        }

        return $data;
    }

    /** @param array<string, string> $grants */
    private function writeGrid(RoleDefinition $role, array $grants): void
    {
        foreach (Permission::cases() as $permission) {
            $value = $grants[$permission->value] ?? Grant::Denied->value;
            if (Grant::tryFrom($value) === null) {
                $value = Grant::Denied->value;
            }
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $role->id, 'permission' => $permission->value],
                ['grant' => $value],
            );
        }

        PermissionMatrix::flush();
    }

    /** @return array<string, string> */
    private function gridOf(RoleDefinition $role): array
    {
        $stored = $role->grid();
        $out = [];
        foreach (Permission::cases() as $permission) {
            $out[$permission->value] = $stored[$permission->value]
                ?? ($role->is_built_in ? PermissionMatrix::defaultGrant($role->key, $permission)->value : Grant::Denied->value);
        }

        return $out;
    }
}
