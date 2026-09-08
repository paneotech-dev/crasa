<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DATA_MODEL section 8. Composed pages: homepage, About, Committees,
 * Membership, landing and legal pages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 200)->unique();
            $table->enum('template', ['standard', 'landing', 'legal'])->default('standard');
            $table->enum('status', ['draft', 'review', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('is_sample');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
