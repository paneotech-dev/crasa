<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Authority;
use App\Models\Country;
use App\Models\User;
use App\Notifications\AccountInvitation;
use App\Support\Locales;
use App\Support\PermissionMatrix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Screen 13. Administrator only, enforced by policy on every action rather
 * than by hiding the navigation item.
 */
class UserAdminController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with(['country', 'authority'])
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('users', 'anonymised_at'), fn ($q) => $q->whereNull('anonymised_at'))
            ->with('customRole')
            ->when($request->string('role')->value(), fn ($q, $v) => $q->where('role', $v))
            ->when($request->string('status')->value(), fn ($q, $v) => $q->where('status', $v))
            ->when($request->boolean('unenrolled'), fn ($q) => $q
                ->where('role', Role::Administrator->value)
                ->whereNull('two_factor_confirmed_at'))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('pages.admin.users.index', [
            'users' => $users,
            'roles' => Role::cases(),
            'roleOptions' => \App\Models\RoleDefinition::orderByDesc('is_built_in')->orderBy('id')->get(),
            'statuses' => UserStatus::cases(),
            // Screen 13's matrix, from the same source the policies read.
            'permissions' => Permission::cases(),
            'matrix' => PermissionMatrix::class,
            'unenrolledCount' => User::where('role', Role::Administrator->value)
                ->whereNull('two_factor_confirmed_at')->count(),
        ]);
    }

    public function invite(Request $request)
    {
        $this->authorize('create', User::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            // Every assignable role, built-in or custom, as on the list's role control.
            'role' => ['required', 'in:'.implode(',', \App\Models\RoleDefinition::pluck('key')->all() ?: array_column(Role::cases(), 'value'))],
            'country_id' => ['nullable', 'exists:countries,id'],
            'authority_id' => ['nullable', 'exists:authorities,id'],
            'preferred_locale' => ['required', 'in:'.implode(',', Locales::SUPPORTED)],
        ]);

        // Inviting into any role but the default is granting a role, so it
        // needs what "Change the role" needs (AssignRoles), else 403.
        abort_unless($data['role'] === Role::Viewer->value || $this->mayAssignRoles($request->user()), 403);

        // No password is set here. The account is invited, and the holder
        // chooses their own password through the reset link. A password
        // typed by an administrator is a password two people know.
        $key = $data['role'];
        $custom = Role::tryFrom($key) === null
            ? \App\Models\RoleDefinition::where('key', $key)->firstOrFail()
            : null;
        $data['role'] = $custom !== null ? Role::from($custom->base_role) : Role::from($key);

        $user = User::create($data + [
            'custom_role_id' => $custom?->id,
            'password' => Str::random(64),
            'status' => UserStatus::Invited,
        ]);

        // An invitation, not a reset: its own wording, the invitee's
        // language, and the seven-day invitations broker.
        $user->notify(new AccountInvitation(
            Password::broker('invitations')->createToken($user),
            $request->user()->name,
        ));

        AuditLog::record(AuditLog::CREATED, $user, ['invited_as' => $key], $request->user());

        return back()->with('status', __('admin.invited', ['email' => $user->email]));
    }

    public function assignRole(Request $request, User $user)
    {
        $this->authorize('assignRole', $user);

        $keys = \App\Models\RoleDefinition::pluck('key')->all() ?: array_column(Role::cases(), 'value');
        $data = $request->validate([
            'role' => ['required', 'in:'.implode(',', $keys)],
        ]);

        $from = $user->customRole?->key ?? $user->role->value;

        if ($from === $data['role']) {
            return back();
        }

        if (! $this->applyRole($request, $user, $data['role'])) {
            return back()->withErrors(['role' => __('fa.two_admins')]);
        }

        return back()->with('status', __('admin.role_changed'));
    }

    /**
     * The role change itself, shared by the list's role control and the
     * edit form so both keep the same rules. False when it would leave fewer
     * than two active administrators.
     */
    private function applyRole(Request $request, User $user, string $key): bool
    {
        $definition = \App\Models\RoleDefinition::where('key', $key)->first();
        $builtIn = Role::tryFrom($key);
        $from = $user->customRole?->key ?? $user->role->value;
        $fromLabel = $user->roleLabel();

        // AP-12: at least two active administrators remain.
        // A suspended administrator is not one of the active two, so moving
        // it to another role leaves the count where it is.
        if ($user->role === Role::Administrator && $user->custom_role_id === null && $builtIn !== Role::Administrator
            && $user->status === UserStatus::Active && $this->activeAdministrators() <= 2) {
            return false;
        }

        $user->update($builtIn !== null
            ? ['role' => $builtIn, 'custom_role_id' => null]
            : ['role' => Role::from($definition->base_role), 'custom_role_id' => $definition->id]);
        $user->unsetRelation('customRole');

        AuditLog::record(AuditLog::ROLE_CHANGED, $user, [
            'from' => $from,
            'to' => $key,
        ], $request->user());

        \App\Support\AdminAlerts::send($request->user(), 'role_changed', [
            'subject' => $user->name, 'from' => $fromLabel, 'to' => $user->roleLabel(),
        ], route('admin.users', ['locale' => app()->getLocale()]));

        return true;
    }

    /** The permission "Change the role" checks (UserPolicy::assignRole), without a subject. */
    private function mayAssignRoles(User $inviter): bool
    {
        return $inviter->hasPermission(Permission::AssignRoles);
    }

    private function activeAdministrators(): int
    {
        return User::query()->withoutGlobalScopes()->where('role', Role::Administrator->value)
            ->whereNull('custom_role_id')->where('status', UserStatus::Active->value)->count();
    }

    public function suspend(Request $request, User $user)
    {
        $this->authorize('update', $user);
        abort_if($request->user()->is($user), 403, 'An administrator does not suspend their own account.');

        if ($user->isAdministrator() && $user->status === UserStatus::Active && $this->activeAdministrators() <= 2) {
            return back()->withErrors(['user' => __('fa.two_admins')]);
        }

        $user->update(['status' => UserStatus::Suspended]);

        AuditLog::record(AuditLog::SUSPENDED, $user, null, $request->user());
        \App\Support\AdminAlerts::send($request->user(), 'suspended', ['subject' => $user->name],
            route('admin.users', ['locale' => app()->getLocale()]));

        return back()->with('status', __('admin.suspended'));
    }

    public function reactivate(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $user->update(['status' => UserStatus::Active]);

        AuditLog::record(AuditLog::REACTIVATED, $user, null, $request->user());
        \App\Support\AdminAlerts::send($request->user(), 'reactivated', ['subject' => $user->name],
            route('admin.users', ['locale' => app()->getLocale()]));

        return back()->with('status', __('admin.reactivated'));
    }

    /**
     * Delete a suspended account (F1). Only once suspended, so deleting is
     * never the first thing done to an account. The row is kept and
     * stripped of the person: the audit log then reads "Former user" for
     * everything they did, and nothing they wrote loses its author link.
     */
    public function destroy(Request $request, User $user)
    {
        $this->authorize('delete', $user);

        if (! $user->isSuspended()) {
            return back()->withErrors(['user' => __('fa.user_delete_suspend_first')]);
        }

        $name = $user->name;

        \Illuminate\Support\Facades\DB::transaction(function () use ($user, $request, $name) {
            // The effective role: a custom role by its name, which outlives
            // the role itself; a built-in role by its key, read by its label.
            $formerRole = $user->customRole?->label('en') ?? $user->role->value;
            AuditLog::record(AuditLog::DELETED, $user, ['former_name' => $name, 'former_role' => $formerRole], $request->user());

            $user->forceFill([
                'name' => __('fa.former_user', [], 'en'),
                'email' => 'former-user-'.$user->id.'@deleted.invalid',
                'password' => Str::random(64),
                'remember_token' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'country_id' => null,
                'authority_id' => null,
                'custom_role_id' => null,
                'role' => Role::Viewer,
                'anonymised_at' => now(),
            ])->saveQuietly();

            $user->committees()->detach();
            $user->notifications()->delete();
        });

        \App\Support\AdminAlerts::send($request->user(), 'deleted', ['subject' => $name],
            route('admin.audit', ['locale' => app()->getLocale()]));

        return redirect()->route('admin.users', ['locale' => app()->getLocale()])
            ->with('status', __('fa.user_deleted', ['name' => $name]));
    }

    /**
     * One account: its details and its audit trail. Entries about the
     * account (it was invited, edited, suspended) and entries by it (what
     * the holder did) are both here, newest first, because a question about
     * a person is usually both at once.
     */
    public function show(Request $request, User $user)
    {
        $this->authorize('viewAny', User::class);
        abort_if($user->anonymised_at !== null, 404);

        $show = in_array($request->query('show'), ['about', 'by'], true) ? $request->query('show') : 'all';
        $morph = $user->getMorphClass();

        $entries = AuditLog::query()
            ->with('user')
            ->where(function ($q) use ($show, $morph, $user) {
                if ($show !== 'by') {
                    $q->orWhere(fn ($about) => $about->where('auditable_type', $morph)->where('auditable_id', $user->id));
                }
                if ($show !== 'about') {
                    $q->orWhere('user_id', $user->id);
                }
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('pages.admin.users.show', [
            'account' => $user->load(['country', 'authority', 'committees', 'customRole']),
            'entries' => $entries,
            'show' => $show,
        ]);
    }

    public function edit(Request $request, User $user)
    {
        $this->authorize('viewAny', User::class);
        $this->authorize('update', $user);
        abort_if($user->anonymised_at !== null, 404);

        return view('pages.admin.users.edit', [
            'account' => $user->load(['committees', 'customRole']),
            'countries' => Country::orderBy('name')->get(),
            'authorities' => Authority::withoutGlobalScopes()->with('country')->orderBy('acronym')->get(),
            'committees' => \App\Models\Committee::withoutGlobalScopes()->orderBy('name')->get(),
            'roleOptions' => \App\Models\RoleDefinition::orderByDesc('is_built_in')->orderBy('id')->get(),
            'seatRoles' => \App\Enums\CommitteeRole::cases(),
            'mayAssignRole' => $request->user()->can('assignRole', $user),
            'moderationScope' => \App\Support\ModeratorScope::of($user),
        ]);
    }

    /**
     * Name, email, authority, country and committee seats, with the role
     * where the editor may change it. The role goes through the same
     * applyRole as the list's control, so the two-administrator rule and
     * "not your own role" hold here as well.
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('viewAny', User::class);
        $this->authorize('update', $user);
        abort_if($user->anonymised_at !== null, 404);

        $seatValues = array_column(\App\Enums\CommitteeRole::cases(), 'value');
        $roleKeys = \App\Models\RoleDefinition::pluck('key')->all() ?: array_column(Role::cases(), 'value');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:160', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($user->id)],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'authority_id' => ['nullable', 'integer', 'exists:authorities,id'],
            'role' => ['nullable', 'in:'.implode(',', $roleKeys)],
            'committees' => ['nullable', 'array'],
            'committees.*' => ['nullable', 'in:'.implode(',', $seatValues)],
            'scope_countries' => ['nullable', 'array'],
            'scope_countries.*' => ['integer', 'exists:countries,id'],
            'scope_committees' => ['nullable', 'array'],
            'scope_committees.*' => ['integer', 'exists:committees,id'],
        ], [], [
            'name' => __('account.name'),
            'email' => __('auth.email'),
        ]);

        // Ruling 5, as a message rather than the model's exception.
        if (! empty($data['authority_id'])) {
            $authorityCountry = Authority::withoutGlobalScopes()->whereKey($data['authority_id'])->value('country_id');
            if ($authorityCountry !== null && (int) $authorityCountry !== (int) ($data['country_id'] ?? 0)) {
                return back()->withInput()->withErrors(['authority_id' => __('d3_people.authority_country_mismatch')]);
            }
        }

        $committeeIds = \App\Models\Committee::withoutGlobalScopes()->pluck('id')->all();
        $seats = collect($data['committees'] ?? [])
            ->filter(fn ($role, $id) => $role !== null && $role !== '' && in_array((int) $id, $committeeIds, true))
            ->mapWithKeys(fn ($role, $id) => [(int) $id => $role])
            ->sortKeys()
            ->all();

        $currentRole = $user->customRole?->key ?? $user->role->value;
        $newRole = $data['role'] ?? $currentRole;

        if ($newRole !== $currentRole && ! $request->user()->can('assignRole', $user)) {
            abort(403);
        }

        $error = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $user, $data, $seats, $newRole, $currentRole) {
            if ($newRole !== $currentRole && ! $this->applyRole($request, $user, $newRole)) {
                return __('fa.two_admins');
            }

            // The model's own audit records name, email, country and authority.
            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'country_id' => $data['country_id'] ?? null,
                'authority_id' => $data['authority_id'] ?? null,
            ]);

            $before = $user->committees()->withoutGlobalScopes()->get()
                ->mapWithKeys(fn ($c) => [$c->id => $c->pivot->role])->sortKeys()->all();

            if ($before !== $seats) {
                $user->committees()->sync(collect($seats)->map(fn ($role) => ['role' => $role])->all());

                AuditLog::record(AuditLog::UPDATED, $user, [
                    'committees' => ['from' => $before, 'to' => $seats],
                ], $request->user());
            }

            // Item 1j: the moderation scope, audited when it changes.
            \App\Support\ModeratorScope::set($user, $data['scope_countries'] ?? [], $data['scope_committees'] ?? [], $request->user());

            return null;
        });

        if ($error !== null) {
            return back()->withInput()->withErrors(['role' => $error]);
        }

        return redirect()->route('admin.users.show', ['locale' => app()->getLocale(), 'user' => $user->id])
            ->with('status', __('d3_people.user_saved', ['name' => $user->name]));
    }

    public function form(Request $request)
    {
        $this->authorize('create', User::class);

        // Only the roles this inviter may assign: the default role alone
        // without AssignRoles.
        $all = $this->mayAssignRoles($request->user());

        return view('pages.admin.users.invite', [
            'roles' => $all ? Role::cases() : [Role::Viewer],
            'roleOptions' => \App\Models\RoleDefinition::orderByDesc('is_built_in')->orderBy('id')
                ->when(! $all, fn ($q) => $q->where('key', Role::Viewer->value))->get(),
            'countries' => Country::orderBy('name')->get(),
            'authorities' => Authority::with('country')->orderBy('acronym')->get(),
        ]);
    }
}
