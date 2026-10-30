<?php

namespace App\Http\Controllers;

use App\Enums\Clearance;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Meeting;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\Project;
use App\Models\Publication;
use App\Support\Locales;
use Illuminate\Http\Response;

/**
 * robots.txt and sitemap.xml, for search engines.
 *
 * Both follow CRASA_NOINDEX (config crasa.noindex), the same switch that puts
 * the noindex header and meta tag on every page. A host with the switch on
 * (staging, a preview) tells every crawler to stay out and has no sitemap;
 * production, with it off, allows indexing and names its sitemap.
 *
 * Every address is built on APP_URL rather than on the host the request
 * came in on, so production names only its own host, never staging's, and
 * a request with a forged Host header cannot change what is listed.
 */
class SiteIndexController extends Controller
{
    /** Section pages under each language, by route name. */
    private const SECTIONS = [
        'home', 'about', 'committees', 'members', 'comparator', 'events', 'publications',
        'projects', 'newsroom', 'contact', 'privacy', 'terms',
    ];

    /** @var list<array{0: string, 1: string, 2: string, 3: mixed}>|null */
    private ?array $records = null;

    public function robots(): Response
    {
        $body = config('crasa.noindex')
            ? "User-agent: *\nDisallow: /\n"
            : "User-agent: *\nDisallow:\n\nSitemap: ".self::root()."/sitemap.xml\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        abort_if((bool) config('crasa.noindex'), 404);

        $entries = [];

        foreach (Locales::SUPPORTED as $locale) {
            foreach (self::SECTIONS as $name) {
                $entries[] = [$this->path($name, ['locale' => $locale]), null];
            }

            $entries[] = ['/'.$locale.'/'.(AccessibilityStatementController::SLUGS[$locale] ?? AccessibilityStatementController::SLUGS['en']), null];

            foreach (Country::query()->orderBy('iso_code')->pluck('iso_code') as $iso) {
                $entries[] = [$this->path('members.show', ['locale' => $locale, 'iso' => strtolower($iso)]), null];
            }

            foreach ($this->records() as [$route, $key, $param, $updated]) {
                $entries[] = [$this->path($route, ['locale' => $locale, $param => $key]), $updated];
            }
        }

        $root = self::root();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($entries as [$path, $updated]) {
            $xml .= '  <url><loc>'.e($root.$path).'</loc>'
                .($updated ? '<lastmod>'.$updated->toDateString().'</lastmod>' : '')
                ."</url>\n";
        }

        $xml .= "</urlset>\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** APP_URL without a trailing slash. */
    public static function root(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    private function path(string $name, array $parameters): string
    {
        return route($name, $parameters, false);
    }

    /**
     * Every published record with its own public page, read with the same
     * conditions as the page that shows it. Read once, listed per language.
     *
     * @return list<array{0: string, 1: string, 2: string, 3: mixed}>
     */
    private function records(): array
    {
        if ($this->records !== null) {
            return $this->records;
        }

        $records = [];
        $named = array_merge(self::SECTIONS, ['home']);

        foreach (Page::published()->whereNotNull('slug')->orderBy('slug')->get(['slug', 'updated_at']) as $page) {
            if (! in_array($page->slug, $named, true)) {
                $records[] = ['page', $page->slug, 'slug', $page->updated_at];
            }
        }

        foreach (Committee::query()->where('is_active', true)->whereNotNull('slug')->orderBy('slug')->get(['slug', 'updated_at']) as $committee) {
            $records[] = ['committees.show', $committee->slug, 'committee', $committee->updated_at];
        }

        foreach (Meeting::query()->where('clearance', Clearance::Public)->whereNotNull('slug')->orderBy('starts_on')->get(['slug', 'updated_at']) as $meeting) {
            $records[] = ['events.show', $meeting->slug, 'meeting', $meeting->updated_at];
        }

        foreach (Publication::listed()->whereNotNull('slug')->orderBy('slug')->get(['slug', 'updated_at']) as $publication) {
            $records[] = ['publications.show', $publication->slug, 'slug', $publication->updated_at];
        }

        foreach (NewsItem::published()->whereNotNull('slug')->orderByDesc('published_at')->get(['slug', 'updated_at']) as $item) {
            $records[] = ['newsroom.show', $item->slug, 'slug', $item->updated_at];
        }

        foreach (Project::published()->whereNotNull('slug')->orderBy('slug')->get(['slug', 'updated_at']) as $project) {
            $records[] = ['projects.show', $project->slug, 'project', $project->updated_at];
        }

        return $this->records = $records;
    }
}
