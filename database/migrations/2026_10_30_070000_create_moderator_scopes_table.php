<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item 1j: a moderator limited to member states and/or committees. One row
 * per member state or committee in the person's scope; no rows means the
 * moderator sees every item, as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderator_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('committee_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'country_id', 'committee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderator_scopes');
    }
};
