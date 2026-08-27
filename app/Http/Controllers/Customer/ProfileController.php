<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /** Shows customer identity and account type. */
    public function edit(Request $request): View
    {
        return view('customer.profile', ['user' => $request->user()]);
    }

    /** Updates natural/legal account identity independently from per-order billing profiles. */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'account_type' => ['required', Rule::in(['natural', 'legal'])],
            'name' => ['required', 'string', 'max:190'],
            'email' => ['nullable', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'national_code' => ['nullable', 'required_if:account_type,natural', 'digits:10', Rule::unique('users', 'national_code')->ignore($user->id)],
            'legal_name' => ['nullable', 'required_if:account_type,legal', 'string', 'max:190'],
            'national_id' => ['nullable', 'required_if:account_type,legal', 'digits:11', Rule::unique('users', 'national_id')->ignore($user->id)],
            'economic_code' => ['nullable', 'string', 'max:20'],
            'registration_number' => ['nullable', 'string', 'max:40'],
        ]);
        $user->update($data);

        return back()->with('success', 'اطلاعات هویتی ذخیره شد.');
    }
}
