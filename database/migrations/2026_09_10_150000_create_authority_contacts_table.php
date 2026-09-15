<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The people at each authority. Extranet item 4.
 *
 * WHY CONSENT IS A COLUMN AND NOT A POLICY DOCUMENT. This table holds named
 * individuals' work email addresses and telephone numbers across fourteen
 * jurisdictions with fourteen different data protection regimes. A row
 * appears in the directory because that person said it may, and the
 * platform records when and who recorded it. Without `listed_at` the row
 * exists and is invisible, which is the correct default for a person whose
 * details somebody else typed in.
 *
 * MEMBERS ONLY, NEVER EXPORTED, NEVER PUBLIC. There is no public route to
 * this table, no CSV of it and no report that reads it, and that is held by
 * test rather than by intention. A directory of regulators' direct lines is
 * exactly the file that must not leave the extranet.
 *
 * WHY IT IS NOT THE USERS TABLE. Most of the people in a national
 * regulator's directory have no account on this platform and never will.
 * Creating dormant accounts to hold a phone number would put fourteen
 * countries' staff lists into the authentication system.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authority_contacts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('authority_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();

            $table->string('name', 160);
            $table->string('role', 160)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('phone', 60)->nullable();

            // Null means this person has not said their details may be
            // listed, so they are not. The default is invisible.
            $table->timestamp('listed_at')->nullable();
            $table->foreignId('listed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('is_sample')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['country_id', 'listed_at']);
        });

        Schema::create('authority_contact_sector', function (Blueprint $table) {
            $table->foreignId('authority_contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained()->cascadeOnDelete();

            $table->unique(['authority_contact_id', 'sector_id'], 'contact_sector_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authority_contact_sector');
        Schema::dropIfExists('authority_contacts');
    }
};
