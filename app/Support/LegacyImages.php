<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * A remote image URL, resolved to the file this platform holds.
 *
 * The stored article bodies point at the old site's CDN. The platform's own
 * copy promises that every download comes from the platform itself, and
 * hot-linking a host nobody here controls is a dependency that breaks the
 * day somebody tidies that bucket.
 *
 * The legacy import already downloaded these files and recorded where each
 * one came from, so this reads that manifest rather than guessing. An image
 * with no local file resolves to null and the renderer drops it: an
 * approved local asset or nothing.
 *
 * The manifest is read once per request and cached in memory. It is a few
 * hundred entries and is not worth a cache store.
 */
class LegacyImages
{
    private const MANIFEST = 'legacy/assets.json';

    private const ROOT = 'images/legacy';

    /** @var array<string, string>|null */
    private ?array $byFilename = null;

    /** The public URL of the local copy, or null if there is not one. */
    public function localUrl(string $remote): ?string
    {
        $path = $this->localPath($remote);

        return $path === null ? null : Storage::disk('public')->url($path);
    }

    public function localPath(string $remote): ?string
    {
        $remote = trim($remote);

        if ($remote === '') {
            return null;
        }

        // Already ours.
        if (str_starts_with($remote, '/storage/') || str_starts_with($remote, self::ROOT)) {
            return ltrim(str_replace('/storage/', '', $remote), '/');
        }

        $name = $this->filename($remote);

        if ($name === '') {
            return null;
        }

        $path = $this->map()[$name] ?? null;

        return $path !== null && Storage::disk('public')->exists($path) ? $path : null;
    }

    /**
     * Webflow's derivative suffix is stripped before matching.
     *
     * A body may point at "…_PHOTO-2026-p-800.jpg" while the file on disk
     * is the original beside it. Both name the same photograph.
     */
    private function filename(string $url): string
    {
        $name = rawurldecode(basename((string) parse_url($url, PHP_URL_PATH)));

        return (string) preg_replace('/-p-\d+(\.[a-z0-9]+)$/i', '$1', $name);
    }

    /** @return array<string, string> */
    private function map(): array
    {
        if ($this->byFilename !== null) {
            return $this->byFilename;
        }

        $this->byFilename = [];

        if (! Storage::disk('local')->exists(self::MANIFEST)) {
            return $this->byFilename;
        }

        $manifest = json_decode(Storage::disk('local')->get(self::MANIFEST), true);

        foreach ($manifest['images'] ?? [] as $url => $asset) {
            if (! isset($asset['path'])) {
                continue;
            }

            $this->byFilename[$this->filename($url)] = $asset['path'];
        }

        return $this->byFilename;
    }
}
