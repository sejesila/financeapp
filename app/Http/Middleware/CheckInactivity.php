<?php
// app/Http/Middleware/CheckInactivity.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckInactivity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $inactivityLimit = config('session.lifetime') * 60; // Convert to seconds
            $lastActivity = session('last_activity_time');

            if ($lastActivity && (time() - $lastActivity) > $inactivityLimit) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('message', 'You have been logged out due to inactivity.');
            }

            // Update last activity time
            session(['last_activity_time' => time()]);
        }

        return $next($request);
    }
}


// Register the middleware in app/Http/Kernel.php (Laravel 10 and below)
// OR in bootstrap/app.php (Laravel 11)

// For Laravel 11 (bootstrap/app.php):
/*
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \App\Http\Middleware\CheckInactivity::class,
    ]);
})
*/

// For Laravel 10 and below (app/Http/Kernel.php):
/*
protected $middlewareGroups = [
    'web' => [
        // ... other middleware
        \App\Http\Middleware\CheckInactivity::class,
    ],
];
*/
