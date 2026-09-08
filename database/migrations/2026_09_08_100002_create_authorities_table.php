<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The national regulatory authority for each member state. One per country
 * in practice, but a separate table because a country may restructure its
 * regulator and the history has to survive that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authorities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('acronym', 30);
            $table->string('website', 255)->nullable();
            $table->smallInteger('established_year')->unsigned()->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->boolean('is_sample')->default(false);
            $table->timestamps();

            $table->index('acronym');
            $table->index('is_sample');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorities');
    }
};
