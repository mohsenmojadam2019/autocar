<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Customer\Models\BillingProfile;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegisterController extends Controller
{
    /** Displays natural/legal customer registration. */
    public function create(): View
    {
        return view('auth.register');
    }

    /** Creates a natural/legal account and an initial immutable-ready billing profile. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account_type' => ['required', Rule::in(['natural', 'legal'])],
            'name' => ['required', 'string', 'max:190'],
            'mobile' => ['required', 'regex:/^09\d{9}$/', 'unique:users,mobile'],
            'email' => ['nullable', 'email', 'max:190', 'unique:users,email'],
            'national_code' => ['nullable', 'required_if:account_type,natural', 'digits:10', 'unique:users,national_code'],
            'legal_name' => ['nullable', 'required_if:account_type,legal', 'string', 'max:190'],
            'national_id' => ['nullable', 'required_if:account_type,legal', 'digits:11', 'unique:users,national_id'],
            'economic_code' => ['nullable', 'string', 'max:20'],
            'registration_number' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::query()->create($data + ['is_active' => true]);
        BillingProfile::query()->create([
            'user_id' => $user->id,
            'type' => $user->account_type,
            'title' => $user->account_type === 'legal' ? 'شرکت اصلی' : 'فاکتور شخصی',
            'is_default' => true,
            'full_name' => $user->account_type === 'natural' ? $user->name : null,
            'national_code' => $user->account_type === 'natural' ? $user->national_code : null,
            'company_name' => $user->account_type === 'legal' ? $user->legal_name : null,
            'national_id' => $user->account_type === 'legal' ? $user->national_id : null,
            'economic_code' => $user->account_type === 'legal' ? $user->economic_code : null,
            'registration_number' => $user->account_type === 'legal' ? $user->registration_number : null,
            'mobile' => $user->mobile,
        ]);

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('account.profile')->with('success', 'حساب شما ایجاد شد. اطلاعات فاکتور را تکمیل کنید.');
    }
}
