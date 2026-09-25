<?php

use App\Http\Controllers\Internal\TlsAllowController;
use App\Http\Controllers\Platform\LandingController;
use App\Platform\Hosts;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Platform hosts (spec §4): every host is derived from the base domain at runtime.
|--------------------------------------------------------------------------
*/

// S0 landing (spec §13): the apex when the platform owns it, www, and app. as the fallback
// while the legacy platform keeps the apex (docs/DISCOVERY.md §3).
foreach (['apex' => Hosts::base(), 'www' => 'www.'.Hosts::base(), 'app' => Hosts::app()] as $key => $host) {
    Route::domain($host)->get('/', LandingController::class)->name('landing.'.$key);
}

// On-demand TLS permission check (spec §11): reached only through Caddy's loopback listener.
Route::get('/internal/tls/allow', TlsAllowController::class)->name('internal.tls.allow');
