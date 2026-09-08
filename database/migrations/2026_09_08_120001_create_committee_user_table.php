<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ruling 2. Clearance is per-committee-role, not per-user.
 *
 * A person's access to a Confidential document derives from the seat they
 * hold on the committee that owns it. A per-user grant list becomes an
 * unmaintainable access list within a year, and the Secretariat has one
 * administrative assistant to maintain it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committee_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('committee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['member', 'chair', 'observer'])->default('member');
            $table->boolean('is_sample')->default(false);
            $table->timestamps();

            $table->unique(['committee_id', 'user_id']);
            $table->index('is_sample');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_user');
    }
};
