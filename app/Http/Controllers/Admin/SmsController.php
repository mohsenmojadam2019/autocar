<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Sms\Services\SmsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SmsController extends Controller
{
    /** Shows delivery logs and transactional templates while keeping provider credentials hidden. */
    public function index(Request $request): View
    {
        $messages = DB::table('sms_messages')->when($request->filled('mobile'), fn ($query) => $query->where('mobile', 'like', '%'.$request->mobile.'%'))->latest()->paginate(30)->withQueryString();
        return view('admin.sms.index', ['messages' => $messages, 'templates' => DB::table('sms_templates')->orderBy('name')->get()]);
    }

    /** Sends one manual service SMS and records its provider delivery log. */
    public function send(Request $request, SmsService $sms): RedirectResponse
    {
        $data = $request->validate(['mobile' => ['required', 'regex:/^09\d{9}$/'], 'message' => ['required', 'string', 'max:1000']]);
        $sms->send($data['mobile'], $data['message']);
        return back()->with('success', 'پیامک برای ارسال ثبت شد.');
    }
}
