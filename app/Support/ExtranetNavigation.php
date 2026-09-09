<?php

namespace App\Support;

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
 * nothing.
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
            ['key' => 'folders', 'route' => 'extranet.folders', 'icon' => 'folder'],
            ['key' => 'calendar', 'route' => 'extranet.calendar', 'icon' => 'calendar'],
            ['key' => 'submissions', 'route' => 'submissions', 'icon' => 'bar-chart'],
            ['key' => 'completeness', 'route' => 'completeness', 'icon' => 'grid'],
            ['key' => 'search', 'route' => 'search', 'icon' => 'search'],
        ];

        $sections = [];

        foreach ($definitions as $definition) {
            if (! Route::has($definition['route'])) {
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
