<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Content\Models\Banner;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BannerInteractionController extends Controller
{
    /** Records a banner impression without storing a raw IP address. */
    public function impression(Request $request, Banner $banner): JsonResponse
    {
        if (! $banner->is_active) {
            return response()->json(['ok' => false], 404);
        }

        $this->record($request, $banner, 'impression');

        return response()->json(['ok' => true]);
    }

    /** Records a click then redirects only to a configured relative or HTTP(S) target. */
    public function click(Request $request, Banner $banner): RedirectResponse
    {
        abort_unless($banner->is_active, 404);
        $this->record($request, $banner, 'click');

        $target = trim((string) $banner->url);
        if ($target === '') {
            return redirect()->route('home');
        }
        if (Str::startsWith($target, '/')) {
            return redirect($target);
        }
        if (filter_var($target, FILTER_VALIDATE_URL) && in_array(parse_url($target, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return redirect()->away($target);
        }

        return redirect()->route('home');
    }

    /** Persists a minimal analytics event with a one-way IP hash for abuse-resistant aggregate reporting. */
    private function record(Request $request, Banner $banner, string $event): void
    {
        DB::table('banner_events')->insert([
            'banner_id' => $banner->id,
            'user_id' => $request->user()?->id,
            'session_token' => $request->session()->getId(),
            'event' => $event,
            'placement' => $banner->placement,
            'ip_hash' => $request->ip() ? hash_hmac('sha256', $request->ip(), (string) config('app.key')) : null,
            'created_at' => now(),
        ]);
    }
}
