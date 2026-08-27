<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SeoRedirect
{
    /** Applies active 301/302/307/308 redirects before storefront routing and increments hit counters. */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin/*') || $request->is('api/*') || $request->is('assets/*')) {
            return $next($request);
        }
        $path = '/'.ltrim($request->path(), '/');
        $redirect = DB::table('seo_redirects')->where('from_path', $path)->where('is_active', true)->first();
        if (! $redirect) {
            return $next($request);
        }
        $status = in_array((int) $redirect->status_code, [301, 302, 307, 308], true) ? (int) $redirect->status_code : 301;
        DB::table('seo_redirects')->where('id', $redirect->id)->increment('hits');

        return redirect()->to($redirect->to_url, $status);
    }
}
