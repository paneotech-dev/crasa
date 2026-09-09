<?php

namespace App\Http\Controllers;

use App\Enums\Theme;
use App\Models\AuditLog;
use App\Services\TwoFactorService;
use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    /** The notifications a person can choose to receive. */
    public const NOTIFICATIONS = [
        'publications', 'submissions', 'committee_documents', 'meetings',
    ];

    public function __construct(private TwoFactorService $twoFactor) {}

    public function show(Request $request)
    {
        return view('pages.account.index', [
            'user' => $request->user(),
            'remainingRecoveryCodes' => $this->twoFactor->remainingRecoveryCodes($request->user()),
            'notifications' => self::NOTIFICATIONS,
        ]);
    }

    /**
     * The back office surface set. Screen 09 puts the control in the header
     * rather than only in settings, because it is a comfort setting a
     * person changes as the light in the room changes, not a preference
     * they set once.
     */
    public function updateTheme(Request $request)
    {
        $data = $request->validate([
            'theme' => ['required', Rule::enum(Theme::class)],
        ]);

        $request->user()->update(['theme' => $data['theme']]);

        return back()->with('status', __('admin.theme_changed'));
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email,'.$user->id],
            'preferred_locale' => ['required', 'in:'.implode(',', Locales::SUPPORTED)],
            'notifications' => ['nullable', 'array'],
            'notifications.*' => ['in:'.implode(',', self::NOTIFICATIONS)],
        ]);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'preferred_locale' => $data['preferred_locale'],
        ]);

        $user->notification_preferences = array_values($data['notifications'] ?? []);
        $user->save();

        return redirect()
            ->route('account', ['locale' => $user->preferred_locale])
            ->with('status', __('account.saved'));
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()],
        ]);

        if (! Hash::check($request->string('current_password')->value(), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('account.current_password_wrong'),
            ]);
        }

        $user->forceFill(['password' => Hash::make($request->string('password')->value())])->save();

        AuditLog::record(AuditLog::PASSWORD_CHANGED, $user, null, $user, $request->ip());

        return redirect()
            ->route('account', ['locale' => app()->getLocale()])
            ->with('status', __('account.password_changed'));
    }
}
