<?php

use App\Http\Controllers\MediaController;
use App\Http\Controllers\Site\AboutController;
use App\Http\Controllers\Site\AreaController;
use App\Http\Controllers\Site\ContactController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\ListingsController;
use App\Http\Controllers\Site\LocaleRedirectController;
use App\Http\Controllers\Site\RobotsController;
use App\Http\Controllers\Site\SitemapController;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SiteHeaders;
use App\Http\Middleware\SitePageCache;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant sites (spec §10, §11, §16): any host that is not a platform host.
| /ar/... and /en/... routes; / redirects to the site's locale.
|--------------------------------------------------------------------------
*/

Route::middleware([ResolveTenant::class, SitePageCache::class, SiteHeaders::class])->group(function (): void {
    Route::get('/', LocaleRedirectController::class)->name('site.root');
    Route::get('/media/{path}', MediaController::class)->where('path', '.*')->name('site.media');
    Route::get('/sitemap.xml', SitemapController::class)->name('site.sitemap');
    Route::get('/robots.txt', RobotsController::class)->name('site.robots');

    Route::prefix('{locale}')->where(['locale' => 'ar|en'])->middleware(SetLocale::class)->group(function (): void {
        Route::get('/', HomeController::class)->name('site.home');
        Route::get('/listings', [ListingsController::class, 'index'])->name('site.listings');
        Route::get('/listings/{ref}', [ListingsController::class, 'show'])->name('site.listing');
        Route::get('/about', AboutController::class)->name('site.about');
        Route::get('/areas/{area}', AreaController::class)->name('site.area');
        Route::get('/contact', ContactController::class)->name('site.contact');
    });

    Route::fallback(fn () => abort(404));
});
