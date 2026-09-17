<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The search field in the top bar.
 *
 * Six record types, grouped by type, because "Botswana" is a member state
 * and an authority and a folder and a submission, and a flat list of
 * eighteen rows makes the reader do the grouping themselves.
 *
 * EVERY GROUP IS GATED SEPARATELY. An editor searching for a person finds
 * nothing, because they cannot open a user record: a search that lists what
 * you cannot reach is a directory of what you are not allowed to see.
 *
 * The public search runs through the search driver and an index. This one
 * does not: the back office searches six tables by title and slug, on a
 * dataset measured in hundreds, and an index would be a second thing to
 * keep in step for no gain a reader would notice.
 */
class AdminSearchController extends Controller
{
    private const LIMIT = 5;

    public function index(Request $request): View
    {
        Gate::authorize('enter-back-office');

        $term = trim($request->string('q')->toString());

        return view('pages.admin.search', [
            'term' => $term,
            'groups' => $term === '' ? [] : $this->groups($term),
        ]);
    }

    /**
     * @return list<array{key: string, label: string, results: list<array{title: string, meta: string, url: string}>}>
     */
    private function groups(string $term): array
    {
        $locale = app()->getLocale();
        /*
         * Not escaped. % and _ in a search term act as wildcards, which is
         * what every other listing on this platform does, and escaping
         * them without an ESCAPE clause made an email address with an
         * underscore in it unfindable rather than safer. The value is
         * bound, so this is a question of behaviour and not of injection.
         */
        $like = '%'.$term.'%';
        $groups = [];

        if (Gate::allows('viewAny', Page::class)) {
            $groups[] = $this->group('pages', Page::query()
                ->with('translations')
                ->where(fn ($q) => $q->where('slug', 'like', $like)
                    ->orWhereHas('translations', fn ($t) => $t->where('field', 'title')->where('value', 'like', $like)))
                ->limit(self::LIMIT)->get()
                ->map(fn (Page $page) => [
                    'title' => $page->translated('title', $page->slug),
                    'meta' => '/'.$page->slug,
                    'url' => route('admin.pages.edit', ['locale' => $locale, 'page' => $page->slug]),
                ]));
        }

        if (Gate::allows('viewAny', Publication::class)) {
            $groups[] = $this->group('publications', Publication::query()
                ->with('translations')
                ->where(fn ($q) => $q->where('slug', 'like', $like)
                    ->orWhereHas('translations', fn ($t) => $t->where('field', 'title')->where('value', 'like', $like)))
                ->limit(self::LIMIT)->get()
                ->map(fn (Publication $p) => [
                    'title' => $p->translated('title', $p->slug),
                    'meta' => $p->published_on?->isoFormat('D MMMM YYYY') ?? '',
                    'url' => route('admin.resources.edit', ['locale' => $locale, 'resource' => 'publications', 'id' => $p->id]),
                ]));
        }

        if (Gate::allows('viewAny', NewsItem::class)) {
            $groups[] = $this->group('news', NewsItem::query()
                ->with('translations')
                ->where(fn ($q) => $q->where('slug', 'like', $like)
                    ->orWhereHas('translations', fn ($t) => $t->where('field', 'title')->where('value', 'like', $like)))
                ->limit(self::LIMIT)->get()
                ->map(fn (NewsItem $item) => [
                    'title' => $item->translated('title', $item->slug),
                    'meta' => $item->date_unknown ? __('events.date_not_stated') : ($item->published_at?->isoFormat('D MMMM YYYY') ?? ''),
                    'url' => route('admin.resources.edit', ['locale' => $locale, 'resource' => 'news', 'id' => $item->id]),
                ]));
        }

        if (Gate::allows('viewAny', User::class)) {
            $groups[] = $this->group('users', User::query()
                ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like))
                ->limit(self::LIMIT)->get()
                ->map(fn (User $user) => [
                    'title' => $user->name,
                    'meta' => $user->email,
                    'url' => route('admin.users', ['locale' => $locale, 'q' => $user->email]),
                ]));
        }

        $groups[] = $this->group('countries', Country::query()
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('iso_code', 'like', $like))
            ->limit(self::LIMIT)->get()
            ->map(fn (Country $country) => [
                'title' => $country->name,
                'meta' => strtoupper($country->iso_code),
                'url' => route('admin.resources.edit', ['locale' => $locale, 'resource' => 'countries', 'id' => $country->id]),
            ]));

        return array_values(array_filter($groups, fn ($group) => $group['results'] !== []));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array{title: string, meta: string, url: string}>  $results
     * @return array{key: string, label: string, results: list<array{title: string, meta: string, url: string}>}
     */
    private function group(string $key, $results): array
    {
        return [
            'key' => $key,
            'label' => __('admin.search_group_'.$key),
            'results' => $results->values()->all(),
        ];
    }
}
