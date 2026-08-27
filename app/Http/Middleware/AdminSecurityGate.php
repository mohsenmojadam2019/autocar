<?php

namespace App\Http\Middleware;

use App\Services\Settings\SettingsRepository;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AdminSecurityGate
{
    public function __construct(private readonly SettingsRepository $settings) {}

    /** Enforces administrator IP rules and TOTP session verification on every admin request. */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('admin/*') && $request->path() !== 'admin') {
            return $next($request);
        }
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $this->enforceIpRules($request, $user->id);
        if ($request->routeIs('2fa.*') || $request->routeIs('account.2fa.*')) {
            return $next($request);
        }

        $require = (bool) $this->settings->get('security.require_admin_2fa', false);
        if ($require && ! $user->two_factor_confirmed_at) {
            return redirect()->route('account.2fa.setup')->with('status', 'برای دسترسی مدیریتی ابتدا احراز هویت دومرحله‌ای را فعال کنید.');
        }
        if ($user->two_factor_confirmed_at) {
            $verifiedAt = (int) $request->session()->get('two_factor_verified_at', 0);
            if ($verifiedAt < now()->subHours(12)->timestamp) {
                $request->session()->put('url.intended', $request->fullUrl());

                return redirect()->route('2fa.challenge');
            }
        }

        return $next($request);
    }

    /** Applies deny rules first, then treats configured allow rules as an allow-list. */
    private function enforceIpRules(Request $request, int $userId): void
    {
        $rules = DB::table('admin_ip_rules')
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $userId))
            ->get();
        if ($rules->isEmpty()) {
            return;
        }

        $ip = (string) $request->ip();
        $denied = $rules->where('is_allowed', false)->contains(fn ($rule) => $this->matchesCidr($ip, $rule->cidr));
        abort_if($denied, 403, 'دسترسی این IP به پنل مدیریت مسدود است.');
        $allows = $rules->where('is_allowed', true);
        if ($allows->isNotEmpty()) {
            abort_unless($allows->contains(fn ($rule) => $this->matchesCidr($ip, $rule->cidr)), 403, 'IP شما در فهرست مجاز پنل مدیریت نیست.');
        }
    }

    /** Matches IPv4/IPv6 addresses against exact IPs or CIDR ranges. */
    private function matchesCidr(string $ip, string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return $ip === $cidr;
        }
        [$network, $prefix] = array_pad(explode('/', $cidr, 2), 2, null);
        $ipBinary = @inet_pton($ip);
        $networkBinary = @inet_pton((string) $network);
        if ($ipBinary === false || $networkBinary === false || strlen($ipBinary) !== strlen($networkBinary)) {
            return false;
        }
        $maxBits = strlen($ipBinary) * 8;
        $prefix = filter_var($prefix, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => $maxBits]]);
        if ($prefix === false) {
            return false;
        }
        $fullBytes = intdiv((int) $prefix, 8);
        $remainingBits = (int) $prefix % 8;
        if (substr($ipBinary, 0, $fullBytes) !== substr($networkBinary, 0, $fullBytes)) {
            return false;
        }
        if ($remainingBits === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($ipBinary[$fullBytes]) & $mask) === (ord($networkBinary[$fullBytes]) & $mask);
    }
}
