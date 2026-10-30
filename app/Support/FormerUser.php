<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Turns an account into a "Former user" (F1).
 *
 * The row is kept and stripped of the person, so the audit log reads
 * "Former user" for everything the holder did and nothing they wrote loses
 * its author link. One place for it, because the back office's delete and
 * crasa:prepare-production must remove an account in the same way.
 *
 * The caller writes the audit entry and wraps this in its transaction.
 */
final class FormerUser
{
    public static function anonymise(User $user): void
    {
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

        // Item 1j's moderation scope belongs to the person, not the row.
        if (Schema::hasTable('moderator_scopes')) {
            DB::table('moderator_scopes')->where('user_id', $user->id)->delete();
        }
    }
}
