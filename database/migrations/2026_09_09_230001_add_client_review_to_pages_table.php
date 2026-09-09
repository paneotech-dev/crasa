<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The client review flag, which is an administration state and nothing else.
 *
 * The privacy notice and the terms of use carry a full draft with named
 * blanks in square brackets: governing law, retention periods, the data
 * protection contact. Until 9 September 2026 that was declared on the page
 * itself, in a paragraph beginning "DRAFT FOR CRASA REVIEW", which meant
 * every reader of a public legal page met the project's internal state
 * before meeting the text.
 *
 * The declaration moves here. needs_client_review is read by the back
 * office only; no public template touches it. An administrator clears it by
 * marking the page reviewed, which stamps who and when so the sign-off is
 * itself a record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->boolean('needs_client_review')->default(false)->after('is_sample');
            $table->timestamp('client_reviewed_at')->nullable()->after('needs_client_review');
            $table->foreignId('client_reviewed_by')->nullable()->after('client_reviewed_at')
                ->constrained('users')->nullOnDelete();

            $table->index('needs_client_review');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropForeign(['client_reviewed_by']);
            $table->dropIndex(['needs_client_review']);
            $table->dropColumn(['needs_client_review', 'client_reviewed_at', 'client_reviewed_by']);
        });
    }
};
