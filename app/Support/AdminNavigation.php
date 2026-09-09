<?php

namespace App\Support;

use App\Enums\Permission;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * The six sections screen 09 draws in the back office header, in the order
 * it draws them:
 *
 *   Dashboard  Content  Media  Regulatory data  Users  Settings
 *
 * A section appears only if the user can open it. That is a convenience,
 * not the access control: every route behind these links re-checks its own
 * policy, so removing a link from here grants nobody anything and hiding
 * one protects nothing.
 */
final class AdminNavigation
{
    /**
     * @return list<array{key: string, label: string, url: string, current: bool}>
     */
    public static function sections(): array
    {
        $locale = app()->getLocale();

        $definitions = [
            ['key' => 'dashboard', 'route' => 'admin.dashboard', 'permission' => null],
            ['key' => 'content', 'route' => 'admin.pages', 'permission' => Permission::ManageContent],
            ['key' => 'translations', 'route' => 'admin.translations', 'permission' => Permission::EditTranslations],
            ['key' => 'media', 'route' => 'admin.media', 'permission' => Permission::ManageContent],
            ['key' => 'data', 'route' => 'admin.indicators', 'permission' => Permission::ViewPublishedIndicators],
            ['key' => 'users', 'route' => 'admin.users', 'permission' => Permission::ManageUsers],
            ['key' => 'settings', 'route' => 'admin.settings', 'permission' => Permission::ManageSettings],
        ];

        $sections = [];

        foreach ($definitions as $definition) {
            /*
             * A section whose route is not registered yet is not drawn.
             * The sections land one at a time and a nav link to a route
             * that does not exist throws rather than 404s, which would
             * take the whole back office down instead of one page.
             */
            if (! Route::has($definition['route'])) {
                continue;
            }

            if ($definition['permission'] !== null && Gate::denies($definition['permission']->value)) {
                continue;
            }

            $url = route($definition['route'], ['locale' => $locale]);

            $sections[] = [
                'key' => $definition['key'],
                'label' => __('admin.section_'.$definition['key']),
                'url' => $url,
                'current' => self::isCurrent($definition['route']),
            ];
        }

        return $sections;
    }

    /**
     * A section stays current across its detail views: editing one page is
     * still Content.
     */
    private static function isCurrent(string $route): bool
    {
        $name = request()->route()?->getName();

        if ($name === null) {
            return false;
        }

        if ($name === $route) {
            return true;
        }

        // admin.pages covers admin.pages.edit, admin.pages.blocks and so on.
        return str_starts_with($name, $route.'.');
    }
}
