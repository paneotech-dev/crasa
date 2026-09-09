<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The navigation, as records rather than as a config file.
 *
 * Screen 14 draws a navigation builder and the contract's stated core
 * requirement is that everything is manageable from the administration. The
 * header read a PHP array and the footer's five columns were written into a
 * Blade template, so the Secretariat could not rename a menu item, reorder
 * one or hide one without a deploy.
 *
 * DESTINATION IS THREE THINGS, NOT ONE FREE-TEXT FIELD.
 *
 *  - route: a named route, resolved in the reader's own language
 *  - page:  a page slug, so a renamed page keeps its menu item
 *  - url:   anything else, including external and mailto
 *
 * A free-text URL field would have been less code and would produce two
 * failures this platform cannot afford: links that 404 after a page is
 * renamed, and links that leave the platform without saying so.
 *
 * An item whose destination no longer resolves is skipped at render rather
 * than rendered broken. A deleted page must not take the site's navigation
 * down with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();

            // Which menu. Two today; the column exists so a third does not
            // need a migration.
            $table->string('menu', 20)->default('header');

            // Nesting. The header is one level, the footer is a heading
            // with links under it, which is the depth screen 02 draws.
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();

            $table->unsignedSmallInteger('position')->default(0);

            $table->string('destination_type', 10)->default('route');
            // Null for a footer column heading, which is a label with
            // children and no destination of its own.
            $table->string('destination', 255)->nullable();

            $table->boolean('is_visible')->default(true);
            $table->boolean('is_sample')->default(false);
            $table->timestamps();

            $table->index(['menu', 'parent_id', 'position']);
            $table->index('is_visible');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
