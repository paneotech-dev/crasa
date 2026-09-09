<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who has said they would use something that is not built.
 *
 * DEMAND CAPTURE, NOT A FEATURE FLAG. Nothing on this platform reads this
 * table to decide what to render. It exists so the Secretariat can answer
 * one question with a number rather than an impression: how many member
 * state representatives said they wanted this, before anybody is asked to
 * pay for it.
 *
 * One row per account per proposal, so a second click is not a second
 * vote. The row goes with the account: an expression of interest from a
 * deleted account is not evidence of anything.
 *
 * Removable in one commit, with the card and the panel it belongs to. See
 * docs/OPEN_ITEMS.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('feature', 60);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'feature']);
            $table->index('feature');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_interests');
    }
};
