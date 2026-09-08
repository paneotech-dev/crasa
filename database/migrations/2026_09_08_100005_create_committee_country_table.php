<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which member states participate in which committee, and in what role.
 *
 * Carries is_sample for the same reason as the committees table: seeded
 * membership is unverified and must be strippable in one action.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committee_country', function (Blueprint $table) {
            $table->id();
            $table->foreignId('committee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['member', 'chair', 'observer'])->default('member');
            $table->boolean('is_sample')->default(false);
            $table->timestamps();

            $table->unique(['committee_id', 'country_id']);
            $table->index('is_sample');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_country');
    }
};
