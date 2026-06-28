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
            $inactivityLimit = config('session.lifetime') * 60;
            $lastActivity    = session('last_activity_time');

            // CheckInactivity.php — remove the regenerateToken() call here
            if ($lastActivity && (time() - $lastActivity) > $inactivityLimit) {
                Auth::logout();
                $request->session()->invalidate(); // this already clears everything
                // ❌ Remove: $request->session()->regenerateToken();

                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Session expired'], 419);
                }
                return redirect()->route('login')
                    ->with('message', 'You have been logged out due to inactivity.');
            }

            // Don't count csrf-token or ping fetches as user activity
            if (!$request->routeIs('csrf.refresh') && !$request->routeIs('session.ping')) {
                session(['last_activity_time' => time()]);
            }
        }

        return $next($request);
    }
}
