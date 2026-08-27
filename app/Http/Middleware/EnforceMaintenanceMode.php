<?php

namespace App\Http\Middleware;

use App\Services\Settings\SettingsRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceMaintenanceMode
{
    public function __construct(private readonly SettingsRepository $settings) {}

    /** Returns a real 503 maintenance response while preserving admin/login/health access. */
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) $this->settings->get('site.maintenance', false)) {
            return $next($request);
        }
        if ($request->is('up') || $request->is('assets/*') || $request->is('login*') || $request->is('logout') || $request->is('two-factor/*')) {
            return $next($request);
        }
        if ($request->user()?->hasPermission('settings.manage')) {
            return $next($request);
        }

        return response()->view('errors.maintenance', status: 503, headers: ['Retry-After' => '300']);
    }
}
