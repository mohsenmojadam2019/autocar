<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Settings\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /** Shows settings grouped by concern while never rendering encrypted secret values. */ public function index(): View { return view('admin.settings.index',['settings'=>Setting::query()->where('is_secret',false)->orderBy('group')->orderBy('key')->get()->groupBy('group')]); }
    /** Persists the curated public setting fields used by storefront/operations. */ public function update(Request $request,SettingsRepository $settings): RedirectResponse { $data=$request->validate(['site_name'=>['required','string','max:190'],'support_phone'=>['nullable','string','max:40'],'minimum_order'=>['nullable','integer','min:0'],'maintenance'=>['nullable','boolean'],'default_gateway'=>['required','in:zarinpal,idpay,zibal,nextpay,payir'],'default_sms_provider'=>['required','in:kavenegar']]); $settings->set('site.name',$data['site_name']); $settings->set('site.support_phone',$data['support_phone']??''); $settings->set('order.minimum',$data['minimum_order']??0,'orders','int'); $settings->set('site.maintenance',$data['maintenance']??false,'general','bool'); $settings->set('payments.default_gateway',$data['default_gateway'],'payments'); $settings->set('sms.default_provider',$data['default_sms_provider'],'sms'); return back()->with('success','تنظیمات ذخیره شد.'); }
}
