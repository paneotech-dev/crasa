<?php

use App\Http\Controllers\LocaleRedirectController;
use App\Http\Middleware\SetLocale;
use App\Support\Locales;
use Illuminate\Support\Facades\Route;

/*
 * Every public page lives under a language segment: /en, /fr, /pt. The root
 * carries no language and redirects to the one the reader last chose, or the
 * one their browser asks for, or English.
 */

Route::get('/', LocaleRedirectController::class)->name('locale.redirect');

Route::prefix('{locale}')
    ->whereIn('locale', Locales::SUPPORTED)
    ->middleware(SetLocale::class)
    ->group(function () {
        Route::view('/', 'pages.home')->name('home');
    });
