<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TrackUserDevice
{
    /** Tracks authenticated browser sessions and enforces device revocation on the next request. */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $request->hasSession()) {
            $fingerprint = hash('sha256', $request->session()->getId().'|'.(string) $request->userAgent());
            $device = DB::table('user_devices')->where('user_id', $user->id)->where('fingerprint', $fingerprint)->first();
            if ($device?->revoked_at) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors(['login' => 'این نشست از پنل امنیتی باطل شده است.']);
            }
            DB::table('user_devices')->updateOrInsert(
                ['user_id' => $user->id, 'fingerprint' => $fingerprint],
                [
                    'name' => substr((string) $request->userAgent(), 0, 190),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'last_seen_at' => now(),
                    'updated_at' => now(),
                    'created_at' => $device?->created_at ?? now(),
                ],
            );
        }

        return $next($request);
    }
}
