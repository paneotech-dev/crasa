<?php

namespace App\Support;

use App\Enums\Permission;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * What a person new to this back office needs to know, in order.
 *
 * One step per section, each naming the section, saying what it is for in
 * a sentence, and linking to it. A step whose section this account cannot
 * open is not shown: an introduction that spends a paragraph on a screen
 * the reader will be refused is worse than no introduction.
 */
final class Onboarding
{
    public const WELCOME = 'welcome';

    /**
     * @return list<array{key: string, title: string, body: string, url: string|null}>
     */
    public static function steps(): array
    {
        $locale = app()->getLocale();

        $definitions = [
            ['key' => 'content', 'route' => 'admin.pages', 'permission' => Permission::ManageContent],
            ['key' => 'translations', 'route' => 'admin.translations', 'permission' => Permission::EditTranslations],
            ['key' => 'media', 'route' => 'admin.media', 'permission' => Permission::ManageContent],
            ['key' => 'data', 'route' => 'admin.data', 'permission' => null],
            ['key' => 'users', 'route' => 'admin.users', 'permission' => Permission::ManageUsers],
            ['key' => 'audit', 'route' => 'admin.audit', 'permission' => Permission::ReadAuditLog],
        ];

        $steps = [];

        foreach ($definitions as $definition) {
            if (! Route::has($definition['route'])) {
                continue;
            }

            if ($definition['permission'] !== null && Gate::denies($definition['permission']->value)) {
                continue;
            }

            $steps[] = [
                'key' => $definition['key'],
                'title' => __('help.step_'.$definition['key'].'_title'),
                'body' => __('help.step_'.$definition['key'].'_body'),
                'url' => route($definition['route'], ['locale' => $locale]),
            ];
        }

        return $steps;
    }

    /** Every dismissible key. Anything not here cannot be dismissed. */
    public static function keys(): array
    {
        return [self::WELCOME];
    }
}
