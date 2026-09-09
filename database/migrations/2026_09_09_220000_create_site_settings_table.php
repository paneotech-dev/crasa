<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site settings: the values that are neither page content nor code.
 *
 * The social accounts were hardcoded in a Blade template, and two of the
 * three were wrong -- x.com/Crasa2C, facebook.com/pages/CRASA and
 * linkedin.com/company/crasa, none of which is CRASA's actual account. A
 * value nobody can edit is a value nobody checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->text('value')->nullable();
            // What kind of thing this is, so the admin form can validate it
            // and render the right control.
            $table->string('type', 20)->default('text');
            $table->string('group', 40)->default('general');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
