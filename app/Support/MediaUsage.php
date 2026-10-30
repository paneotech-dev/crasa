<?php

namespace App\Support;

use App\Models\MediaAsset;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\PageBlock;
use Illuminate\Support\Facades\Schema;

/**
 * Where an asset is used.
 *
 * Screen 14 puts a "Used on" column on every asset and colours it red when
 * the answer is "Not used". That column is the difference between a library
 * somebody can prune and one that only ever grows, and it is also what
 * stops a delete taking an image off a live page.
 *
 * Blocks store the reference as the literal string "media:{id}", not as a
 * bare number, which is what makes this a search rather than a guess: a
 * LIKE for "12" matches asset 120 and any block with 12 anywhere in its
 * settings, and a LIKE for "media:12" matches "media:120" too, so the
 * candidates found here are confirmed against the decoded settings before
 * being reported.
 */
final class MediaUsage
{
    /**
     * The slot is the config/media-slots.php key the use draws the image
     * in (hero slider block, news item), or null when it has no slot.
     *
     * @return list<array{label: string, url: string|null, slot: string|null}>
     */
    public static function for(MediaAsset $asset): array
    {
        $reference = $asset->reference();
        $usages = [];

        $blocks = PageBlock::query()
            ->with('page.translations')
            ->where('settings', 'like', '%'.$reference.'%')
            ->get();

        foreach ($blocks as $block) {
            if (! self::settingsReference($block->settings ?? [], $reference)) {
                continue;
            }

            $page = $block->page;

            if ($page === null) {
                continue;
            }

            $usages[] = [
                'label' => ($page->translate('title')?->value ?? $page->slug).' — '.$block->type()->label(),
                'url' => route('admin.pages.edit', ['locale' => app()->getLocale(), 'page' => $page->slug]),
                'slot' => $block->type() === \App\Enums\BlockType::Hero ? 'hero' : null,
            ];
        }

        if (Schema::hasColumn('news_items', 'media_asset_id')) {
            NewsItem::query()
                ->where('media_asset_id', $asset->id)
                ->with('translations')
                ->get()
                ->each(function (NewsItem $item) use (&$usages) {
                    $usages[] = [
                        'label' => $item->translate('title')?->value ?? $item->slug,
                        'url' => null,
                        'slot' => 'news',
                    ];
                });
        }

        /*
         * News items point at the asset by its stored path (the news form's
         * photograph field), not by an id column, so they are found by it.
         */
        if (Schema::hasColumn('news_items', 'hero_image_path') && $asset->path) {
            NewsItem::query()
                ->where('hero_image_path', $asset->path)
                ->with('translations')
                ->get()
                ->each(function (NewsItem $item) use (&$usages) {
                    $usages[] = [
                        'label' => $item->translate('title')?->value ?? $item->slug,
                        'url' => null,
                        'slot' => 'news',
                    ];
                });
        }

        return $usages;
    }

    public static function isUsed(MediaAsset $asset): bool
    {
        return self::for($asset) !== [];
    }

    /**
     * Whether the reference genuinely appears as a value in the settings,
     * rather than merely as a substring of one.
     *
     * @param  array<string, mixed>  $settings
     */
    private static function settingsReference(array $settings, string $reference): bool
    {
        foreach ($settings as $value) {
            if (is_array($value) && self::settingsReference($value, $reference)) {
                return true;
            }

            if (is_string($value) && $value === $reference) {
                return true;
            }
        }

        return false;
    }
}
