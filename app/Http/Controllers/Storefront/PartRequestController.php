<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PartRequestController extends Controller
{
    /** Shows the part-finder form for products unavailable in normal search. */
    public function create(): View
    {
        return view('storefront.part-request');
    }

    /** Creates a structured part request with optional selected vehicle trim. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mobile' => ['required', 'regex:/^09\d{9}$/'],
            'part_name' => ['required', 'string', 'max:190'],
            'oem_code' => ['nullable', 'string', 'max:100'],
            'vehicle_trim_id' => ['nullable', 'exists:vehicle_trims,id'],
            'description' => ['nullable', 'string', 'max:3000'],
        ]);
        DB::table('part_requests')->insert($data + ['user_id' => $request->user()?->id, 'status' => 'new', 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'درخواست قطعه ثبت شد و توسط پشتیبانی بررسی می‌شود.');
    }
}
