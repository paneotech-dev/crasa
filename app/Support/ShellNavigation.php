<?php

namespace App\Support;

use App\Enums\Permission;
use App\Models\Committee;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * The sidebar, for both the back office and the extranet.
 *
 * SCREEN 09 DRAWS A HORIZONTAL SECTION BAR AND IT IS OVERRULED. Ruled on
 * 10 September 2026 and logged as a disclosed variation in
 * docs/OPEN_ITEMS.md: with twenty-four destinations the bar wraps into a
 * control strip two and three rows deep, and the back office is the surface
 * the Secretariat uses every day. Screen 09's content is untouched -- the
 * figures, the recent activity, the sample-data count are all still there.
 * The shell around it changed.
 *
 * GROUPED, BECAUSE FLAT AT THIS LENGTH IS A LIST RATHER THAN A NAVIGATION.
 * The extranet rail was flat on screen 04's authority and stays grouped
 * here for the same reason it stopped being flat: it now carries a row per
 * committee the reader sits on, and seven items with no headings is where
 * a reader starts scanning rather than reaching.
 *
 * A GROUP APPEARS ONLY IF SOMETHING IN IT DOES, AND AN ITEM ONLY IF THE
 * READER MAY OPEN IT. That is a convenience and not the access control:
 * every route behind these links re-checks its own policy, so removing a
 * link grants nobody anything and hiding one protects nothing. Hidden items
 * are absent rather than disabled, because a disabled control is a promise
 * that it will work later.
 */
final class ShellNavigation
{
    /**
     * The back office sidebar.
     *
     * @return list<array{key: string, label: string, items: list<array<string, mixed>>}>
     */
    public static function administration(): array
    {
        /*
         * Nothing at all for a reader who cannot enter the back office.
         *
         * The dashboard row carries no permission of its own, because
         * everybody who is in here may see it. Without this line that made
         * it visible to everybody who is not, and a viewer's sidebar
         * offered them one link to a 403.
         */
        if (Gate::denies('enter-back-office')) {
            return [];
        }

        return self::build([
            'overview' => [
                ['key' => 'dashboard', 'route' => 'admin.dashboard', 'icon' => 'dashboard', 'permission' => null],
                ['key' => 'audit', 'route' => 'admin.audit', 'icon' => 'audit', 'permission' => Permission::ReadAuditLog],
            ],
            'content' => [
                ['key' => 'pages', 'route' => 'admin.pages', 'icon' => 'pages', 'permission' => Permission::ManageContent],
                ['key' => 'publications', 'route' => 'admin.resources', 'params' => ['resource' => 'publications'], 'icon' => 'publications', 'permission' => Permission::ManageContent],
                ['key' => 'newsroom', 'route' => 'admin.resources', 'params' => ['resource' => 'news'], 'icon' => 'newsroom', 'permission' => Permission::ManageContent],
                ['key' => 'events', 'route' => 'extranet.calendar', 'icon' => 'events', 'permission' => Permission::ManageMeetings],
                ['key' => 'media', 'route' => 'admin.media', 'icon' => 'media', 'permission' => Permission::ManageContent],
                ['key' => 'navigation', 'route' => 'admin.menus', 'icon' => 'navigation', 'permission' => Permission::ManageContent],
                ['key' => 'translations', 'route' => 'admin.translations', 'icon' => 'translations', 'permission' => Permission::EditTranslations],
                ['key' => 'legal', 'route' => 'admin.pages', 'query' => ['template' => 'legal'], 'icon' => 'legal', 'permission' => Permission::ManageContent],
            ],
            'regulatory' => [
                ['key' => 'countries', 'route' => 'admin.resources', 'params' => ['resource' => 'countries'], 'icon' => 'members', 'permission' => null],
                ['key' => 'authorities', 'route' => 'admin.resources', 'params' => ['resource' => 'authorities'], 'icon' => 'authorities', 'permission' => null],
                ['key' => 'committees', 'route' => 'admin.resources', 'params' => ['resource' => 'committees'], 'icon' => 'committees', 'permission' => null],
                ['key' => 'indicators', 'route' => 'admin.resources', 'params' => ['resource' => 'indicators'], 'icon' => 'indicators', 'permission' => null],
                ['key' => 'instruments', 'route' => 'admin.resources', 'params' => ['resource' => 'instruments'], 'icon' => 'instruments', 'permission' => null],
                ['key' => 'submissions', 'route' => 'admin.submissions', 'icon' => 'submissions', 'permission' => Permission::ValidateSubmission],
                ['key' => 'reports', 'route' => 'admin.reports', 'icon' => 'reports', 'permission' => Permission::ExportReports],
            ],
            'people' => [
                ['key' => 'users', 'route' => 'admin.users', 'icon' => 'users', 'permission' => Permission::ManageUsers],
                ['key' => 'delegates', 'route' => 'admin.meetings', 'icon' => 'delegates', 'permission' => Permission::ManageMeetings],
                ['key' => 'confirmations', 'route' => 'admin.confirmations', 'icon' => 'confirmations', 'permission' => Permission::ManageContent],
            ],
            'platform' => [
                ['key' => 'settings', 'route' => 'admin.settings', 'icon' => 'settings', 'permission' => Permission::ManageSettings],
                ['key' => 'samples', 'route' => 'admin.samples', 'icon' => 'samples', 'permission' => Permission::ManageSettings],
            ],
        ], 'admin');
    }

    /**
     * The extranet sidebar.
     *
     * The committees group is one row per seat the reader holds. A
     * committee member had no route to their own workspace from anywhere
     * until 9 September 2026, and a panel on one page is a route only from
     * that page.
     *
     * @return list<array{key: string, label: string, items: list<array<string, mixed>>}>
     */
    public static function extranet(?User $user = null): array
    {
        $user ??= auth()->user();

        $groups = [
            'workspace' => [
                ['key' => 'dashboard', 'route' => 'extranet.dashboard', 'icon' => 'dashboard', 'permission' => null],
                ['key' => 'submissions', 'route' => 'submissions', 'icon' => 'submissions', 'permission' => Permission::SubmitCountryReturn],
                ['key' => 'folders', 'route' => 'extranet.folders', 'icon' => 'folder', 'permission' => Permission::OpenMemberDocuments],
            ],
            'committees' => [],
            'region' => [
                ['key' => 'completeness', 'route' => 'completeness', 'icon' => 'completeness', 'permission' => Permission::ViewPublishedIndicators],
                ['key' => 'comparator', 'route' => 'comparator', 'icon' => 'comparator', 'permission' => null],
                ['key' => 'calendar', 'route' => 'extranet.calendar', 'icon' => 'calendar', 'permission' => null],
            ],
            'account' => [
                ['key' => 'account', 'route' => 'account', 'icon' => 'settings', 'permission' => null],
            ],
        ];

        foreach (self::seats($user) as $committee) {
            $groups['committees'][] = [
                'key' => 'committee-'.$committee->slug,
                'route' => 'extranet.committee',
                'params' => ['committee' => $committee->slug],
                'icon' => 'committees',
                'permission' => null,
                'label' => $committee->name,
            ];
        }

        return self::build($groups, 'extranet');
    }

    /** @return \Illuminate\Support\Collection<int, Committee> */
    private static function seats(?User $user)
    {
        if ($user === null) {
            return collect();
        }

        return $user->committees()->orderBy('name')->get(['committees.id', 'committees.slug', 'committees.name']);
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $groups
     * @return list<array{key: string, label: string, items: list<array<string, mixed>>}>
     */
    private static function build(array $groups, string $namespace): array
    {
        $locale = app()->getLocale();
        $built = [];

        foreach ($groups as $group => $definitions) {
            $items = [];

            foreach ($definitions as $definition) {
                /*
                 * A section whose route is not registered is not drawn. The
                 * sections land one at a time, and a link to a route that
                 * does not exist throws rather than 404s, which takes the
                 * whole shell down instead of one page.
                 */
                if (! Route::has($definition['route'])) {
                    continue;
                }

                if ($definition['permission'] !== null && Gate::denies($definition['permission']->value)) {
                    continue;
                }

                $params = array_merge(['locale' => $locale], $definition['params'] ?? []);
                $url = route($definition['route'], $params + ($definition['query'] ?? []));

                $items[] = [
                    'key' => $definition['key'],
                    'label' => $definition['label'] ?? __($namespace.'.nav_'.$definition['key']),
                    'url' => $url,
                    'icon' => $definition['icon'],
                    'current' => self::isCurrent($definition, $params),
                ];
            }

            if ($items === []) {
                continue;
            }

            $built[] = [
                'key' => $group,
                'label' => __($namespace.'.side_group_'.$group),
                'items' => $items,
            ];
        }

        return $built;
    }

    /**
     * Whether this row is the page being read.
     *
     * Route name alone is not enough here: seven back office rows are the
     * same route with a different resource, and marking all seven current
     * because one of them is would tell the reader nothing.
     *
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $params
     */
    private static function isCurrent(array $definition, array $params): bool
    {
        $request = request();

        if (! $request->routeIs($definition['route']) && ! $request->routeIs($definition['route'].'.*')) {
            return false;
        }

        foreach ($definition['params'] ?? [] as $key => $value) {
            if ((string) $request->route($key) !== (string) $value) {
                return false;
            }
        }

        // A row that only differs by query string, such as the legal pages
        // filter, is current only when that query string is on the request.
        foreach ($definition['query'] ?? [] as $key => $value) {
            if ((string) $request->query($key) !== (string) $value) {
                return false;
            }
        }

        // And a row with no parameters of its own is not current while a
        // sibling row's parameter is on the request.
        if (($definition['params'] ?? []) === [] && $request->route('resource') !== null) {
            return false;
        }

        return true;
    }
}
