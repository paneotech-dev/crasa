<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DATA_MODEL section 7. Written on content create, update and delete,
 * submission transitions, role changes, login and failed login, and
 * downloads of non-public documents.
 *
 * user_id is nullable: a failed login has no authenticated user, and neither
 * does a scheduled task. There is no updated_at, because a row is never
 * updated. The model refuses updates and deletes outright.
 *
 * ip_address is 45 characters, which holds a full IPv6 address including an
 * IPv4-mapped form. Full IPv6 support is contracted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80);
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
