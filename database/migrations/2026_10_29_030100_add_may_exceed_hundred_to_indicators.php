<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * A reading note on indicators whose figure per 100 inhabitants runs above
 * 100 in ordinary markets: one person holds several mobile subscriptions.
 * Mauritius at 118 mobile subscriptions per 100 reads as an error without it.
 *
 * A flag on the indicator, so the note follows the indicator onto every
 * surface that shows it, whatever the member state.
 */
return new class extends Migration
{
    private const SLUGS = ['mobile-cellular-subscriptions', 'active-mobile-broadband-subscriptions'];

    public function up(): void
    {
        if (! Schema::hasColumn('indicators', 'may_exceed_hundred')) {
            Schema::table('indicators', function (Blueprint $table) {
                $table->boolean('may_exceed_hundred')->default(false)->after('source_url');
            });
        }

        DB::table('indicators')->whereIn('slug', self::SLUGS)->update(['may_exceed_hundred' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('indicators', 'may_exceed_hundred')) {
            Schema::table('indicators', function (Blueprint $table) {
                $table->dropColumn('may_exceed_hundred');
            });
        }
    }
};
