<?php

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

            // Check if user has been inactive
            if ($lastActivity && (time() - $lastActivity) > $inactivityLimit) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Session expired'], 419);
                }

                return redirect()->route('login')
                    ->with('message', 'You have been logged out due to inactivity.');
            }

            // Update last activity time on every request
            session(['last_activity_time' => time()]);
        }

        return $next($request);
    }
}
