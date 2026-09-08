<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The fourteen CRASA member states. Seeded, not user-editable.
 *
 * There is no is_sample column. Everything in this table is a verifiable
 * fact about a SADC member state, so nothing here is demonstration data.
 * The English name lives here; French and Portuguese names go in the
 * translations table, per docs/DATA_MODEL.md section 5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->char('iso_code', 2)->unique();
            $table->string('name', 120);
            // Comma-separated platform locale codes: the languages this member
            // state is served in. Not the state's full language inventory.
            $table->string('official_languages', 60);
            $table->date('joined_at')->nullable();
            $table->string('flag_path', 255)->nullable();
            $table->string('hero_image_path', 255)->nullable();
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
