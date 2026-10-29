<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * The accessibility statement, G5.
 *
 * Each language has its own address (/en/accessibility, /fr/accessibilite,
 * /pt/acessibilidade). The three addresses answer in every language, so
 * the language switcher, which swaps only the first segment, never leads
 * to a 404; links are built with url(), which gives the reader's own.
 *
 * The text is in lang/{en,fr,pt}/g.php rather than a page record: it is a
 * statement about this build, reviewed with each accessibility pass, and
 * must stay identical in structure across the three languages.
 */
class AccessibilityStatementController extends Controller
{
    /** The address of the statement in each language. */
    public const SLUGS = [
        'en' => 'accessibility',
        'fr' => 'accessibilite',
        'pt' => 'acessibilidade',
    ];

    public function show(): View
    {
        return view('pages.accessibility-statement', [
            'statement' => __('g.statement'),
        ]);
    }

    /** The statement's address in the given language, or the reader's. */
    public static function url(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $slug = self::SLUGS[$locale] ?? self::SLUGS['en'];

        return url($locale.'/'.$slug);
    }
}
