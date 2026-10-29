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
            'role' => ['required', 'in:'.implode(',', array_column(Role::cases(), 'value'))],
            'country_id' => ['nullable', 'exists:countries,id'],
            'authority_id' => ['nullable', 'exists:authorities,id'],
            'preferred_locale' => ['required', 'in:'.implode(',', Locales::SUPPORTED)],
        ]);

        // No password is set here. The account is invited, and the holder
        // chooses their own password through the reset link. A password
        // typed by an administrator is a password two people know.
        $user = User::create($data + [
            'password' => Str::random(64),
            'status' => UserStatus::Invited,
        ]);

        // An invitation, not a reset: its own wording, the invitee's
        // language, and the seven-day invitations broker.
        $user->notify(new AccountInvitation(
            Password::broker('invitations')->createToken($user),
            $request->user()->name,
        ));

        AuditLog::record(AuditLog::CREATED, $user, ['invited_as' => $data['role']], $request->user());

        return back()->with('status', __('admin.invited', ['email' => $user->email]));
    }

    public function assignRole(Request $request, User $user)
    {
        $this->authorize('assignRole', $user);

        $keys = \App\Models\RoleDefinition::pluck('key')->all() ?: array_column(Role::cases(), 'value');
        $data = $request->validate([
            'role' => ['required', 'in:'.implode(',', $keys)],
        ]);

        $definition = \App\Models\RoleDefinition::where('key', $data['role'])->first();
        $builtIn = Role::tryFrom($data['role']);
        $from = $user->customRole?->key ?? $user->role->value;
        $fromLabel = $user->roleLabel();

        if ($from === $data['role']) {
            return back();
        }

        // AP-12: at least two active administrators remain.
        if ($user->role === Role::Administrator && $user->custom_role_id === null && $builtIn !== Role::Administrator
            && $this->activeAdministrators() <= 2) {
            return back()->withErrors(['role' => __('fa.two_admins')]);
        }

        $user->update($builtIn !== null
            ? ['role' => $builtIn, 'custom_role_id' => null]
            : ['role' => Role::from($definition->base_role), 'custom_role_id' => $definition->id]);
        $user->unsetRelation('customRole');

        AuditLog::record(AuditLog::ROLE_CHANGED, $user, [
            'from' => $from,
            'to' => $data['role'],
        ], $request->user());

        \App\Support\AdminAlerts::send($request->user(), 'role_changed', [
            'subject' => $user->name, 'from' => $fromLabel, 'to' => $user->roleLabel(),
        ], route('admin.users', ['locale' => app()->getLocale()]));

        return back()->with('status', __('admin.role_changed'));
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

    public function form(Request $request)
    {
        $this->authorize('create', User::class);

        return view('pages.admin.users.invite', [
            'roles' => Role::cases(),
            'countries' => Country::orderBy('name')->get(),
            'authorities' => Authority::with('country')->orderBy('acronym')->get(),
        ]);
    }
}
