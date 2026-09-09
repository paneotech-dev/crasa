<?php

namespace App\Support;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * The extranet's own navigation, as screen 04 draws it.
 *
 * The screen puts a navy left rail beside the workspace — 220 to 280px, the
 * mark with an "Extranet" label, flat links with Lucide icons, an active
 * state of --c-navy-mid with a 3px --c-green left edge, and the reader's own
 * clearance pinned to the bottom.
 *
 *   <nav aria-label="Extranet" style="flex:1 1 220px;max-width:280px;
 *        background:var(--c-navy);color:var(--c-cream);padding:var(--s-5) 0;
 *        display:flex;flex-direction:column;gap:var(--s-6)">
 *
 * The extranet had been rendering the back office's horizontal header
 * instead, which is screen 09's pattern on a surface that has its own.
 *
 * FLAT, NOT GROUPED. Screen 04 draws seven links and no group headings.
 * Grouping seven items into three named groups adds headings without adding
 * navigation, and the screen governs.
 *
 * A section appears only if the reader can open it. That is a convenience,
 * not the access control: every route behind these links re-checks its own
 * policy, so removing a link grants nobody anything and hiding one protects
 * nothing. The filter was added on 9 September 2026; before that this note
 * described an intention rather than the code, and every reader saw every
 * section.
 */
final class ExtranetNavigation
{
    /**
     * @return list<array{key: string, label: string, url: string, icon: string, current: bool}>
     */
    public static function sections(): array
    {
        $locale = app()->getLocale();

        // The order screen 04 draws them in. Dashboard is the extranet
        // landing page; Regulatory data is the submission workspace, which
        // only a member state representative or the Secretariat reaches.
        $definitions = [
            ['key' => 'folders', 'route' => 'extranet.folders', 'icon' => 'folder', 'permission' => Permission::OpenMemberDocuments],
            ['key' => 'calendar', 'route' => 'extranet.calendar', 'icon' => 'calendar', 'permission' => null],
            ['key' => 'submissions', 'route' => 'submissions', 'icon' => 'bar-chart', 'permission' => Permission::SubmitCountryReturn],
            ['key' => 'completeness', 'route' => 'completeness', 'icon' => 'grid', 'permission' => Permission::ViewPublishedIndicators],
            ['key' => 'search', 'route' => 'search', 'icon' => 'search', 'permission' => null],
        ];

        $user = auth()->user();
        $sections = [];

        foreach ($definitions as $definition) {
            if (! Route::has($definition['route'])) {
                continue;
            }

            /*
             * The filter this class has claimed since it was written and
             * did not have. Every section was shown to everybody, so a
             * committee member's rail carried "Regulatory data", which is
             * the member state submission workspace and returns 403 for
             * them, and "Reporting", which is the completeness matrix.
             *
             * Still not the access control. Every route behind these links
             * re-checks its own policy, so removing a link grants nobody
             * anything and hiding one protects nothing. It is the
             * difference between a rail that describes the reader's work
             * and one that describes the platform.
             */
            if ($definition['permission'] !== null
                && ($user === null || ! $user->hasPermission($definition['permission']))) {
                continue;
            }

            $sections[] = [
                'key' => $definition['key'],
                'label' => __('extranet.nav_'.$definition['key']),
                'url' => route($definition['route'], ['locale' => $locale]),
                'icon' => $definition['icon'],
                'current' => request()->routeIs($definition['route'])
                    || request()->routeIs($definition['route'].'.*'),
            ];
        }

        return $sections;
    }

    /**
     * What the reader may reach, in their own words.
     *
     * Screen 04 pins this to the bottom of the rail and states it plainly:
     * "You can reach Public and Member material, and only Botswana's own
     * country folders." A reader who does not know their own clearance
     * reads an empty folder list as a fault rather than as a boundary.
     *
     * @return array{level: string, scope: string}|null
     */
    public static function clearance(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $country = $user->country;

        return [
            'level' => __('extranet.clearance_'.($user->role?->value ?? 'guest')),
            'scope' => $country
                ? __('extranet.clearance_country', ['country' => $country->name])
                : __('extranet.clearance_regional'),
        ];
    }
}
