<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
           // \App\Http\Middleware\CheckInactivity::class,
            \App\Http\Middleware\EnsureResourceOwnership::class,

        ]);

    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'webhook/mpesa-sms',
            'csrf-token',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (
            \Illuminate\Session\TokenMismatchException $e,
            \Illuminate\Http\Request $request
        ) {
            $request->session()->regenerateToken();

            if ($request->routeIs('login') || $request->is('login')) {
                return redirect()->route('login')
                    ->withInput($request->except('password'))
                    ->with('info', 'Your session expired. Please try logging in again.');
            }

            return redirect()->route('login')
                ->with('info', 'Your session expired. Please log in again.');
        });
    })->create();
