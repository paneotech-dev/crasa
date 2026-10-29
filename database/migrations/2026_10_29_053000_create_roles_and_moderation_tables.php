<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Support\PermissionMatrix;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Day 2: roles kept as data, and moderated publishing.
 *
 * users.role becomes a string so the moderator and auditor presets fit; it
 * still holds a built-in role, which carries country scope and two-factor.
 * A role created on the roles screen is held in users.custom_role_id and
 * its permission grid replaces the built-in one.
 *
 * approvals is one table for every moderated type (pages, news,
 * publications, events): who submitted, who decided, when, the comment.
 * approval_rules says per type whether one approval is required.
 *
 * Presets are matched by key, never by id, and inserted only when missing,
 * so running this twice changes nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 40)->default('viewer')->change();
        });

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('key', 60)->unique();
                $table->string('name_en', 120);
                $table->string('name_fr', 120)->nullable();
                $table->string('name_pt', 120)->nullable();
                $table->string('base_role', 40)->default('viewer');
                $table->boolean('is_built_in')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('role_permissions')) {
            Schema::create('role_permissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
                $table->string('permission', 60);
                $table->string('grant', 16)->default('denied');
                $table->unique(['role_id', 'permission']);
            });
        }

        if (! Schema::hasColumn('users', 'custom_role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('custom_role_id')->nullable()->after('role')->constrained('roles')->restrictOnDelete();
            });
        }

        $names = [
            'administrator' => ['Administrator', 'Administrateur', 'Administrador'],
            'moderator' => ['Moderator', 'Modérateur', 'Moderador'],
            'editor' => ['Editor', 'Éditeur', 'Editor'],
            'member_state_rep' => ['Member State Representative', 'Représentant d\'État membre', 'Representante de Estado-membro'],
            'committee_member' => ['Committee Member', 'Membre de comité', 'Membro de comité'],
            'viewer' => ['Viewer', 'Lecteur', 'Leitor'],
            'auditor' => ['Auditor', 'Auditeur', 'Auditor'],
        ];

        foreach ($names as $key => [$en, $fr, $pt]) {
            if (DB::table('roles')->where('key', $key)->exists()) {
                continue;
            }

            $id = DB::table('roles')->insertGetId([
                'key' => $key, 'name_en' => $en, 'name_fr' => $fr, 'name_pt' => $pt,
                'base_role' => $key, 'is_built_in' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            foreach (Permission::cases() as $permission) {
                DB::table('role_permissions')->insert([
                    'role_id' => $id,
                    'permission' => $permission->value,
                    'grant' => PermissionMatrix::defaultGrant($key, $permission)->value,
                ]);
            }
        }

        if (! Schema::hasTable('approvals')) {
            Schema::create('approvals', function (Blueprint $table) {
                $table->id();
                $table->string('approvable_type', 120);
                $table->unsignedBigInteger('approvable_id');
                $table->string('state', 16)->default('in_review');
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('decided_at')->nullable();
                $table->text('note')->nullable();
                $table->text('comment')->nullable();
                $table->json('snapshot')->nullable();
                $table->timestamps();
                $table->index(['approvable_type', 'approvable_id']);
                $table->index('state');
            });
        }

        if (! Schema::hasTable('approval_rules')) {
            Schema::create('approval_rules', function (Blueprint $table) {
                $table->id();
                $table->string('content_type', 40)->unique();
                $table->string('mode', 16)->default('one');
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        foreach (['publications', 'news', 'events', 'pages'] as $type) {
            if (! DB::table('approval_rules')->where('content_type', $type)->exists()) {
                DB::table('approval_rules')->insert([
                    'content_type' => $type, 'mode' => 'one', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // Held mail: what the platform would send if mail were on.
        if (! Schema::hasTable('held_mail')) {
            Schema::create('held_mail', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('recipient', 190);
                $table->string('subject', 255);
                $table->text('body');
                $table->string('status', 16)->default('held');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('held_mail');
        Schema::dropIfExists('approval_rules');
        Schema::dropIfExists('approvals');

        if (Schema::hasColumn('users', 'custom_role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('custom_role_id');
            });
        }

        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');

        DB::table('users')->whereNotIn('role', ['administrator', 'editor', 'member_state_rep', 'committee_member', 'viewer'])
            ->update(['role' => 'viewer']);
    }
};
