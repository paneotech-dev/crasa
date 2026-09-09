<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The extranet, IN-02 and WS-09. Screens 04, 11 and 12.
 *
 * A folder belongs to a member state, or to a committee, or to neither
 * (which means the Secretariat's own). Those three cases are the whole
 * access model, and they are columns rather than a permissions table
 * because a per-user grant list is the thing that rots: somebody leaves an
 * authority and their grants stay behind.
 *
 * Screen 12: "Each member state reaches only its own folders. The boundary
 * is enforced on the server, and it is stated on screen rather than left to
 * be inferred from an empty list."
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('folders')->cascadeOnDelete();

            // Exactly one of these, or neither for a Secretariat folder.
            $table->foreignId('country_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('committee_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('slug', 120);

            // The floor. A document may be more restricted than its folder;
            // it is never less, which is checked on save.
            $table->string('clearance', 20)->default('member');

            $table->boolean('is_sample')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['parent_id', 'slug']);
            $table->index(['country_id', 'committee_id']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('folder_id')->nullable()->after('documentable_id')->constrained()->nullOnDelete();
            $table->foreignId('country_id')->nullable()->after('folder_id')->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->after('country_id')->constrained('users')->nullOnDelete();
            $table->index('folder_id');
        });

        /*
         * Meetings, screen 12's calendar and screen 04's workspace.
         *
         * READ ONLY IN THIS PLATFORM as far as operations go. A meeting
         * record carries what it is, when it is and which committee owns
         * it, with an agenda and a workplan. It carries nothing about
         * transfers, hotels, interpretation or catering: that is the Event
         * Management Platform's work and a separate commercial agreement.
         */
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('committee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug', 160)->unique();
            $table->string('kind', 40)->default('committee');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('location')->nullable();
            $table->text('agenda')->nullable();
            $table->string('clearance', 20)->default('member');

            // Where a delegate registers. The platform links out; it does
            // not run the registration.
            $table->string('registration_url')->nullable();

            $table->boolean('is_sample')->default(false);
            $table->timestamps();

            $table->index('starts_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');

        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('folder_id');
            $table->dropConstrainedForeignId('country_id');
            $table->dropConstrainedForeignId('uploaded_by');
        });

        Schema::dropIfExists('folders');
    }
};
