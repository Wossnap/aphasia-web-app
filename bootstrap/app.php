<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::prefix('admin')->group(base_path('routes/admin.php'));
        }
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\SetDisplayTimezone::class,
        ]);

        // The browser writes this itself, so it must survive Laravel's cookie
        // encryption to be readable as a plain zone name on the way back in.
        $middleware->encryptCookies(except: [
            \App\Http\Middleware\SetDisplayTimezone::COOKIE,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            '/api/transcribe',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (\Illuminate\Session\TokenMismatchException $e) {
            \Illuminate\Support\Facades\Log::error('CSRF Token Mismatch Error', [
                'url' => request()->fullUrl(),
                'input' => request()->except(['password', '_token']),
            ]);
        });
    })->create();
