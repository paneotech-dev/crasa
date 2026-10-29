<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * INC-11, UX-02: "Extranet" is "Member area" in every language. The
 * interface strings are in lang/; the footer menu item that points at the
 * member area carries its own stored labels, so they change here.
 *
 * Matched by what the item points at (the extranet route) and by the old
 * label, so a label an editor has since changed by hand is left alone, and
 * running it twice changes nothing. The URL is unchanged.
 */
return new class extends Migration
{
    private const LABELS = [
        'en' => 'Member area',
        'fr' => 'Espace membres',
        'pt' => 'Área de membros',
    ];

    public function up(): void
    {
        $this->swap(from: fn () => 'Extranet', to: fn (string $locale) => self::LABELS[$locale]);
    }

    public function down(): void
    {
        $this->swap(from: fn (string $locale) => self::LABELS[$locale], to: fn () => 'Extranet');
    }

    private function swap(Closure $from, Closure $to): void
    {
        $ids = DB::table('menu_items')
            ->where('destination_type', 'route')
            ->where('destination', 'extranet')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        foreach (array_keys(self::LABELS) as $locale) {
            DB::table('translations')
                ->where('translatable_type', 'App\Models\MenuItem')
                ->whereIn('translatable_id', $ids)
                ->where('field', 'label')
                ->where('locale', $locale)
                ->where('value', $from($locale))
                ->update(['value' => $to($locale), 'updated_at' => now()]);
        }
    }
};
