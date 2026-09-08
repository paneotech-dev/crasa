<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DATA_MODEL section 7. The access columns on the standard Laravel table.
 *
 * country_id null means Secretariat or administrator. two_factor_secret is
 * encrypted at rest by the model's encrypted cast, using the application key
 * and AES-256-CBC, so it is never written in clear even by a raw insert
 * through the model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('authority_id')->nullable()->after('country_id')->constrained()->nullOnDelete();
            $table->enum('role', [
                'administrator',
                'editor',
                'member_state_rep',
                'committee_member',
                'viewer',
            ])->default('viewer')->after('password');
            $table->text('two_factor_secret')->nullable()->after('role');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
            $table->char('preferred_locale', 2)->default('en')->after('two_factor_confirmed_at');
            $table->timestamp('last_seen_at')->nullable()->after('preferred_locale');

            $table->index('role');
            $table->index(['country_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropForeign(['authority_id']);
            $table->dropIndex(['role']);
            $table->dropIndex(['country_id', 'role']);
            $table->dropColumn([
                'country_id', 'authority_id', 'role', 'two_factor_secret',
                'two_factor_confirmed_at', 'preferred_locale', 'last_seen_at',
            ]);
        });
    }
};
