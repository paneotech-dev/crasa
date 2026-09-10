<?php

namespace App\Http\Controllers;

use App\Models\LegacyRecord;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A legacy URL that a regulator cited, still answering. WS-12.
 *
 * WHY 301 AND NOT 302. The legacy site is being replaced, not moved
 * temporarily. A 301 is what tells a search engine to transfer the ranking
 * and what tells a browser to stop asking. CRASA's guidelines are cited in
 * national consultation documents; those citations have to keep resolving
 * after the old host is switched off.
 *
 * WHY THIS IS A FALLBACK AND NOT A MIDDLEWARE. Only a request that matched
 * no route should be looked up here. A middleware would run this query on
 * every request to every page for the sake of the handful that need it.
 *
 * THE LOCALE. Legacy URLs carry no language. A reader arriving from an old
 * link gets the language their browser asks for, which is what the locale
 * redirect does for the site root, rather than English by assumption.
 */
class LegacyRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $record = LegacyRecord::pathFor($request->path());

        if ($record === null || $record->new_path === null) {
            throw new NotFoundHttpException;
        }

        $locale = Locales::detect($request);

        [$path, $query] = array_pad(explode('?', ltrim($record->new_path, '/'), 2), 2, null);

        $target = url('/'.$locale.'/'.$path);

        // The listing rules carry a query string: /post-category/tenders
        // lands on the newsroom already filtered to tenders, rather than on
        // the newsroom's first page with the reader to find it again.
        if ($query !== null && $query !== '') {
            $target .= '?'.$query;
        }

        return redirect()->to($target, 301);
    }
}
