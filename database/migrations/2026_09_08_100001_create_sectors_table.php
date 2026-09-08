<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The three regulated sectors: telecommunications, broadcasting, postal.
 * Fixed set, seeded. Adding a fourth is a decision, not a commit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sectors', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name', 80);
            // A Lucide icon name. One icon library across the platform.
            $table->string('icon', 60);
            // The name of a custom property from public/css/tokens.css, so a
            // sector's accent changes with the token layer and never as a
            // hard-coded colour in a template.
            $table->string('accent_token', 40);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sectors');
    }
};
