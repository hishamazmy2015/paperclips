<?php

use App\Http\Middleware\AppLocale;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SiteHeaders;
use App\Http\SiteResponses;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Tenant sites are registered last: platform hosts (apex, app., admin., api.) match
        // their own routes first, every other host falls through to the site routes (§11).
        then: function (): void {
            Route::middleware('web')->group(base_path('routes/site.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Only Caddy can reach php-fpm (its port is never published), so its X-Forwarded-* are trusted.
        $middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo(fn (Request $request): string => route('start'));
        $middleware->alias([
            'app.locale' => AppLocale::class,
            'tenant' => ResolveTenant::class,
            'site.locale' => SetLocale::class,
            'site.headers' => SiteHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // A missing page on a tenant site is the theme's 404, not the framework's (§10, §16).
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (TenantContext::current() !== null && ! $request->expectsJson()) {
                return app(SiteResponses::class)->notFound();
            }

            return null;
        });
    })->create();
