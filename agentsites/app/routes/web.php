<?php

use App\Http\Controllers\App\CodeController;
use App\Http\Controllers\App\EmailCodeController;
use App\Http\Controllers\App\GoogleController;
use App\Http\Controllers\App\HomeController;
use App\Http\Controllers\App\LogoutController;
use App\Http\Controllers\App\MagicLinkController;
use App\Http\Controllers\App\PhoneCodeController;
use App\Http\Controllers\App\ReminderOptOutController;
use App\Http\Controllers\App\ShareController;
use App\Http\Controllers\App\StartController;
use App\Http\Controllers\App\SuccessController;
use App\Http\Controllers\Internal\TlsAllowController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\Platform\LandingController;
use App\Livewire\Onboarding\Wizard;
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

// app.{base}: sign-in (S1), the onboarding wizard (S2–S4), success (S5), the agent's home (spec §13).
Route::domain(Hosts::app())->middleware('app.locale')->group(function (): void {
    Route::get('/start', StartController::class)->name('start');
    Route::post('/auth/email', [EmailCodeController::class, 'send'])->middleware('throttle:otp-send')->name('auth.email');
    Route::post('/auth/phone', [PhoneCodeController::class, 'send'])->middleware('throttle:otp-send')->name('auth.phone');
    Route::get('/start/code', [CodeController::class, 'show'])->name('code');
    Route::post('/start/code', [CodeController::class, 'verify'])->middleware('throttle:otp-verify')->name('code.verify');
    Route::get('/auth/magic/{otp}', MagicLinkController::class)->middleware('signed')->name('auth.magic');
    Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
    Route::get('/reminders/opt-out', ReminderOptOutController::class)->middleware('signed')->name('reminders.opt-out');

    Route::middleware('auth')->group(function (): void {
        Route::get('/onboarding', Wizard::class)->name('onboarding');
        Route::get('/onboarding/success', SuccessController::class)->name('onboarding.success');
        Route::match(['get', 'post'], '/share/{channel}', ShareController::class)->where('channel', 'whatsapp|open|copy')->name('share');
        Route::get('/home', HomeController::class)->name('home');
        Route::post('/logout', LogoutController::class)->name('logout');
        Route::get('/media/{path}', MediaController::class)->where('path', '.*')->name('app.media');
    });
});

// On-demand TLS permission check (spec §11): reached only through Caddy's loopback listener.
Route::get('/internal/tls/allow', TlsAllowController::class)->name('internal.tls.allow');
