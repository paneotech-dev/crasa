<?php

use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Translation;
use Illuminate\Database\Migrations\Migration;

/*
 * Item 1h: the privacy notice rewritten for the cookie choice, with the
 * retention periods the code defines and the services that receive data.
 *
 * A language is replaced only while it still holds the text seeded in
 * September (database/seeders/data/privacy-notice-2026-09.php). A text the
 * Secretariat has edited since is left alone, and running this twice
 * changes nothing. The page keeps needs_client_review: it is a draft for
 * CRASA's legal review, and the back office says so.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->swap(
            require database_path('seeders/data/privacy-notice-2026-09.php'),
            require database_path('seeders/data/privacy-notice.php'),
        );
    }

    public function down(): void
    {
        $this->swap(
            require database_path('seeders/data/privacy-notice.php'),
            require database_path('seeders/data/privacy-notice-2026-09.php'),
        );
    }

    /**
     * @param  array<string, string>  $from
     * @param  array<string, string>  $to
     */
    private function swap(array $from, array $to): void
    {
        $page = Page::where('slug', 'privacy')->first();
        if ($page === null) {
            return;
        }

        $blockIds = PageBlock::where('page_id', $page->id)->pluck('id');
        $changed = false;

        foreach (Translation::where('translatable_type', PageBlock::class)
            ->whereIn('translatable_id', $blockIds)
            ->where('field', 'body')
            ->get() as $row) {
            $old = $from[$row->locale] ?? null;
            if ($old !== null && isset($to[$row->locale]) && $this->same((string) $row->value, $old)) {
                $row->value = $to[$row->locale];
                $row->save();
                $changed = true;
            }
        }

        if ($changed) {
            // A new "last updated" date on the page, and the draft stays
            // flagged for CRASA's review in the back office.
            $page->forceFill(['needs_client_review' => true, 'client_reviewed_at' => null, 'client_reviewed_by' => null, 'updated_at' => now()])->save();
        }
    }

    /** Equal apart from line endings and surrounding space: the stored text came through a form with CRLF. */
    private function same(string $a, string $b): bool
    {
        $norm = fn (string $s) => trim(str_replace("\r\n", "\n", $s));

        return $norm($a) === $norm($b);
    }
};
