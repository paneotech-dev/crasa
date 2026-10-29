<?php

namespace App\Console\Commands;

use App\Models\MediaAsset;
use App\Models\NewsItem;
use App\Services\MediaLibrary;
use App\Support\PublicCache;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/**
 * The photograph crasa.org itself shows for a newsroom item that has none
 * here (item 8f: never a labelled placeholder).
 *
 * WHY SO SHORT A LIST. Eight published items have no photograph. crasa.org
 * was read item by item: for six it shows nothing of its own (a logo, or
 * the photographs of other items in its sidebar), and for the 15th AGM its
 * one photograph is already the home page hero, which the same-photograph
 * rule keeps off the newsroom card that sits under it. One item has a
 * photograph of its own, the 13th AGM group photograph from Ezulwini. The
 * others keep the CRASA mark on white.
 *
 * SAFE ON LIVE AND IDEMPOTENT. The file goes through the media library
 * (EXIF stripped, renditions built, deduplicated on its checksum), so a
 * second run finds the same asset. Alt text is written only where a
 * language has none, and an item's photograph is set only while it has
 * none, so a choice made in the back office is never overwritten.
 */
class ImportNewsPhotographs extends Command
{
    protected $signature = 'news:import-photographs {--dry-run : Say what would change and change nothing}';

    protected $description = 'Import the crasa.org photograph of newsroom items that have none, into the media library';

    /** slug => [source url, [locale => alt text]] */
    public const PHOTOGRAPHS = [
        'crasa-13th-annual-general-meeting' => [
            'https://cdn.prod.website-files.com/5fb8ce4adbd6ad2ccc1423e7/66b9d7660d9abdb0575af6e8_CRASA0010000.png',
            [
                'en' => 'Delegates to the 13th CRASA Annual General Meeting in a group photograph outside the venue in Ezulwini, Eswatini, in front of a banner of the Eswatini Communications Commission.',
                'fr' => "Les délégués à la 13e Assemblée générale annuelle de la CRASA en photo de groupe devant le lieu de la réunion à Ezulwini, en Eswatini, devant une bannière de l'Eswatini Communications Commission.",
                'pt' => 'Os delegados à 13.ª Assembleia Geral Anual da CRASA numa fotografia de grupo à porta do local da reunião em Ezulwini, Essuatíni, diante de uma faixa da Eswatini Communications Commission.',
            ],
        ],
    ];

    public function handle(MediaLibrary $library): int
    {
        $changed = false;

        foreach (self::PHOTOGRAPHS as $slug => [$url, $alt]) {
            $item = NewsItem::where('slug', $slug)->first();

            if ($item === null) {
                $this->line("{$slug}: not in this database, skipped.");

                continue;
            }

            if ($item->hero_image_path && ! $this->isOurs($item->hero_image_path, $url)) {
                $this->line("{$slug}: already has a photograph, left as it is.");

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("{$slug}: would import {$url}.");

                continue;
            }

            $asset = $this->asset($library, $url);

            if ($asset === null) {
                $this->error("{$slug}: {$url} could not be downloaded as an image.");

                continue;
            }

            foreach ($alt as $locale => $text) {
                if (trim((string) ($asset->translationValuesFor('alt')[$locale] ?? '')) === '') {
                    $asset->setTranslation('alt', $locale, $text);
                }
            }

            if ($item->hero_image_path !== $asset->path) {
                $item->forceFill(['hero_image_path' => $asset->path])->save();
                $changed = true;
            }

            $this->info("{$slug}: media asset {$asset->id}, {$asset->path}.");
        }

        if ($changed) {
            PublicCache::flush();
        }

        return self::SUCCESS;
    }

    /** The asset already imported from this address, or a fresh import. */
    private function asset(MediaLibrary $library, string $url): ?MediaAsset
    {
        $existing = MediaAsset::where('source_url', $url)->first();

        if ($existing !== null) {
            return $existing;
        }

        $response = Http::timeout(60)->get($url);

        if (! $response->successful() || ! str_starts_with((string) $response->header('Content-Type'), 'image/')) {
            return null;
        }

        $temp = tempnam(sys_get_temp_dir(), 'news-photo-');
        file_put_contents($temp, $response->body());

        try {
            $file = new UploadedFile($temp, basename(rawurldecode(parse_url($url, PHP_URL_PATH))), null, null, true);
            $asset = $library->store($file);
        } finally {
            @unlink($temp);
        }

        $asset->forceFill([
            'rights_holder' => $asset->rights_holder ?: 'CRASA',
            'licence' => $asset->licence ?: "The client's own material, from crasa.org",
            'source_url' => $asset->source_url ?: $url,
        ])->save();

        return $asset;
    }

    private function isOurs(string $path, string $url): bool
    {
        return MediaAsset::where('path', $path)->where('source_url', $url)->exists();
    }
}
