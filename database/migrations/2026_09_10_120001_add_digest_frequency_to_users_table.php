<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * How often an account wants its digest. RP-05, extranet item 6.
 *
 * notification_preferences already records which triggers an account wants.
 * This records how often the email arrives, which is a separate question:
 * a regulator may want to be told about every guideline in their sector and
 * still want one email a week rather than nine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function ($table) {
            $table->string('digest_frequency', 16)->default('daily')->after('notification_preferences');
            $table->timestamp('digest_sent_at')->nullable()->after('digest_frequency');
        });
    }

    public function down(): void
    {
        Schema::table('users', function ($table) {
            $table->dropColumn(['digest_frequency', 'digest_sent_at']);
        });
    }
};
