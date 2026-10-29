<?php

use App\Enums\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Evening pass: approval is a permission per content type (publications,
 * news, events, pages, social posts), and social posts join the approval
 * flow with the rule "one approval".
 *
 * Every role, built in or created on the roles screen, gets each type
 * permission with the grant it held for "approve content", so nobody who
 * could approve yesterday loses it and nobody gains it. Matched by role
 * key and permission code; rows that exist are left alone.
 */
return new class extends Migration
{
    private const TYPES = [
        'content.approve_publications',
        'content.approve_news',
        'content.approve_events',
        'content.approve_pages',
        'content.approve_social',
    ];

    public function up(): void
    {
        if (Schema::hasTable('roles') && Schema::hasTable('role_permissions')) {
            foreach (DB::table('roles')->get(['id', 'key']) as $role) {
                $grant = DB::table('role_permissions')->where('role_id', $role->id)
                    ->where('permission', Permission::ApproveContent->value)->value('grant');

                if ($grant === null) {
                    $grant = \App\Support\PermissionMatrix::defaultGrant($role->key, Permission::ApproveContent)->value;
                }

                foreach (self::TYPES as $permission) {
                    $exists = DB::table('role_permissions')->where('role_id', $role->id)
                        ->where('permission', $permission)->exists();

                    if (! $exists) {
                        DB::table('role_permissions')->insert([
                            'role_id' => $role->id, 'permission' => $permission, 'grant' => $grant,
                        ]);
                    }
                }
            }
        }

        if (Schema::hasTable('approval_rules') && ! DB::table('approval_rules')->where('content_type', 'social')->exists()) {
            DB::table('approval_rules')->insert([
                'content_type' => 'social', 'mode' => 'one', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->whereIn('permission', self::TYPES)->delete();
        }

        if (Schema::hasTable('approval_rules')) {
            DB::table('approval_rules')->where('content_type', 'social')->delete();
        }
    }
};
