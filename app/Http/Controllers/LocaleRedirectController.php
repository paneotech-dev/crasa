<?php

namespace App\Http\Controllers;

use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The site root names no language. Send the reader to the one they last
 * chose, or the one their browser asks for, or English.
 */
class LocaleRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect()->to(Locales::urlFor(Locales::detect($request), $request));
    }
}
