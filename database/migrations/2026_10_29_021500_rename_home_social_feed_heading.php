<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The homepage "Follow us" section becomes "CRASA on social media": the
 * three channels with their handles. Only the heading changes here; the
 * block keeps its place, its settings and its networks.
 *
 * A heading an editor has already rewritten is left alone: only the
 * original wording is replaced, in each language, so this can run twice.
 */
return new class extends Migration
{
    /** @var array<string, array{0: string, 1: string}> locale => [old, new] */
    private const HEADINGS = [
        'en' => ['Follow us', 'CRASA on social media'],
        'fr' => ['Nous suivre', 'La CRASA sur les réseaux sociaux'],
        'pt' => ['Siga-nos', 'A CRASA nas redes sociais'],
    ];

    public function up(): void
    {
        $this->swap(0, 1);
    }

    public function down(): void
    {
        $this->swap(1, 0);
    }

    private function swap(int $from, int $to): void
    {
        $blockIds = DB::table('page_blocks')
            ->join('pages', 'pages.id', '=', 'page_blocks.page_id')
            ->where('pages.slug', 'home')
            ->where('page_blocks.block_type', 'social_feed')
            ->pluck('page_blocks.id');

        if ($blockIds->isEmpty()) {
            return;
        }

        foreach (self::HEADINGS as $locale => $pair) {
            DB::table('translations')
                ->where('translatable_type', 'App\\Models\\PageBlock')
                ->whereIn('translatable_id', $blockIds->all())
                ->where('field', 'heading')
                ->where('locale', $locale)
                ->where('value', $pair[$from])
                ->update(['value' => $pair[$to], 'updated_at' => now()]);
        }
    }
};
