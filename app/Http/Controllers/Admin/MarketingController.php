<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Marketing\Services\CampaignService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MarketingController extends Controller
{
    /** Shows coupons, segments and SMS campaigns with delivery counters. */
    public function index(): View
    {
        return view('admin.marketing.index', [
            'campaigns' => DB::table('sms_campaigns')->leftJoin('customer_segments', 'customer_segments.id', '=', 'sms_campaigns.segment_id')->select('sms_campaigns.*', 'customer_segments.name as segment_name')->latest('sms_campaigns.id')->paginate(25),
            'segments' => DB::table('customer_segments')->where('is_active', true)->orderBy('name')->get(),
            'coupons' => DB::table('coupons')->latest()->limit(20)->get(),
        ]);
    }

    /** Creates a consent-aware SMS campaign draft. */
    public function storeCampaign(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:190'], 'segment_id' => ['nullable', 'exists:customer_segments,id'], 'message' => ['required', 'string', 'max:1000'], 'scheduled_at' => ['nullable', 'date'], 'rate_per_minute' => ['required', 'integer', 'min:1', 'max:600']]);
        DB::table('sms_campaigns')->insert($data + ['status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'کمپین ایجاد شد.');
    }

    /** Materializes recipients from explicit marketing consent and the selected segment rules. */
    public function buildRecipients(int $campaign, CampaignService $service): RedirectResponse
    {
        $count = $service->buildRecipients($campaign);
        return back()->with('success', number_format($count).' گیرنده واجد شرایط به کمپین اضافه شد.');
    }

    /** Stops any future campaign sends immediately. */
    public function stop(int $campaign, CampaignService $service): RedirectResponse
    {
        $service->stop($campaign);
        return back()->with('success', 'کمپین متوقف شد.');
    }

    /** Creates a reusable customer segment from supported server-side rules. */
    public function storeSegment(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:190'], 'customer_group' => ['nullable', 'string', 'max:32'], 'min_lifetime_value' => ['nullable', 'integer', 'min:0']]);
        $rules = array_filter(['customer_group' => $data['customer_group'] ?? null, 'min_lifetime_value' => $data['min_lifetime_value'] ?? null], fn ($value) => $value !== null && $value !== '');
        DB::table('customer_segments')->insert(['name' => $data['name'], 'rules' => json_encode($rules, JSON_UNESCAPED_UNICODE), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'سگمنت مشتری ایجاد شد.');
    }
}
