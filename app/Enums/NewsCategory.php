<?php

namespace App\Enums;

/**
 * The categories the approved screens actually use. Screen 02 carries
 * "Tender" and "Public consultation" as kickers alongside plain news, and
 * screen 07 carries communiques.
 *
 * DATA_MODEL section 6 gives news_items no category column. Added here
 * because the newsroom is contracted with category filtering and a
 * regulator's tender notice is not the same kind of item as a news story.
 */
enum NewsCategory: string
{
    case News = 'news';
    case Tender = 'tender';
    case Consultation = 'consultation';
    case Communique = 'communique';

    /*
     * Added 10 September 2026 with the legacy import. Eleven of the
     * legacy site's items are press releases, a grouping CRASA keeps
     * separate from news, tenders and consultations, and folding them
     * into news loses a distinction the Secretariat maintains.
     */
    case PressRelease = 'press_release';

    public function labelKey(): string
    {
        return 'news.category_'.$this->value;
    }
}
