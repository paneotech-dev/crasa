<?php

use App\Models\Committee;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A committee's terms of reference are prose, so they are translatable.
 *
 * committees.mandate was a plain column on a trilingual platform.
 * CLAUDE.md's multilingual rules put body prose in the translations table
 * and are explicit that a per-language field never becomes a title_fr
 * column; a Francophone regulator reading the ARPTC's seat on a committee
 * met the English mandate with no notice and no way to translate it.
 *
 * The column's contents move to the translations table as English before
 * the column is dropped, so nothing written before today is lost. down()
 * puts the English back and leaves the French and Portuguese behind, which
 * is stated rather than hidden: a reversal loses what only the new shape
 * could hold.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('committees', 'mandate')) {
            return;
        }

        $type = (new Committee)->getMorphClass();

        DB::table('committees')
            ->whereNotNull('mandate')
            ->where('mandate', '!=', '')
            ->orderBy('id')
            ->each(function ($committee) use ($type) {
                DB::table('translations')->updateOrInsert(
                    [
                        'translatable_type' => $type,
                        'translatable_id' => $committee->id,
                        'locale' => 'en',
                        'field' => 'mandate',
                    ],
                    [
                        'value' => $committee->mandate,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            });

        Schema::table('committees', function (Blueprint $table) {
            $table->dropColumn('mandate');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('committees', 'mandate')) {
            return;
        }

        Schema::table('committees', function (Blueprint $table) {
            $table->text('mandate')->nullable()->after('sector_id');
        });

        $type = (new Committee)->getMorphClass();

        DB::table('translations')
            ->where('translatable_type', $type)
            ->where('field', 'mandate')
            ->where('locale', 'en')
            ->orderBy('id')
            ->each(fn ($row) => DB::table('committees')
                ->where('id', $row->translatable_id)
                ->update(['mandate' => $row->value]));
    }
};
