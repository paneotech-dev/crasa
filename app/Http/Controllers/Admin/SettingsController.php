<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SiteSetting;
use App\Support\SocialAccounts;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Site settings, CM-05.
 *
 * The things that belong to the site rather than to a page: the
 * Secretariat's own contact details, CRASA's social accounts, the legacy
 * site's address. They were a mixture of hardcoded strings in a Blade
 * template and strings in three lang files, so changing the Secretariat's
 * telephone number was a deploy.
 *
 * ADMINISTRATOR ONLY. Screen 13's matrix grants ManageSettings to the
 * administrator and to nobody else, and an editor changing the address on
 * every page of a regulator's site is not an editorial act.
 *
 * NO SETTING IS CREATED HERE. The keys are the ones the seeder registers,
 * so a value the platform does not read cannot be typed into the database
 * and then wondered about. A new setting is a commit.
 */
class SettingsController extends Controller
{
    /**
     * The groups, in the order the screen shows them, and the rule each
     * value is held to.
     *
     * @var array<string, array<string, list<string>>>
     */
    private const FIELDS = [
        'contact' => [
            'contact_address_1' => ['nullable', 'string', 'max:200'],
            'contact_address_2' => ['nullable', 'string', 'max:200'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:60'],
        ],
        'social' => [
            'social_x' => ['nullable', 'url', 'max:255'],
            'social_linkedin' => ['nullable', 'url', 'max:255'],
            'social_facebook' => ['nullable', 'url', 'max:255'],
            'social_youtube' => ['nullable', 'url', 'max:255'],
        ],
        'general' => [
            'legacy_website' => ['nullable', 'url', 'max:255'],
        ],
    ];

    public function edit(): View
    {
        Gate::authorize(Permission::ManageSettings->value);

        return view('pages.admin.settings.edit', [
            'groups' => self::FIELDS,
            'values' => SiteSetting::all_values(),
            'networks' => SocialAccounts::NETWORKS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::ManageSettings->value);

        $rules = [];

        foreach (self::FIELDS as $fields) {
            foreach ($fields as $key => $rule) {
                $rules[$key] = $rule;
            }
        }

        $data = $request->validate($rules);

        $changed = [];

        foreach ($data as $key => $value) {
            $setting = SiteSetting::where('key', $key)->first();

            if ($setting === null) {
                // A key the seeder has not registered. Not created here:
                // see the class note.
                continue;
            }

            $value = is_string($value) ? trim($value) : $value;
            $value = $value === '' ? null : $value;

            if ($setting->value === $value) {
                continue;
            }

            $changed[$key] = ['from' => $setting->value, 'to' => $value];
            $setting->update(['value' => $value]);
        }

        if ($changed !== []) {
            /*
             * One audit row for the change, naming every key that moved and
             * what it moved from. A settings screen that saves eleven
             * values and writes eleven rows makes the log unreadable, and
             * one that writes none makes "who changed the address" a
             * question with no answer.
             */
            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => AuditLog::UPDATED,
                'auditable_type' => SiteSetting::class,
                'auditable_id' => null,
                'changes' => $changed,
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);
        }

        return back()->with('status', __('admin.settings_saved'));
    }
}
