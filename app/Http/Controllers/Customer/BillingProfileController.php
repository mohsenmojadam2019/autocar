<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Customer\Models\BillingProfile;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BillingProfileController extends Controller
{
    /** Lists invoice identities owned by the current user. */
    public function index(Request $request): View
    {
        return view('customer.billing-profiles', ['profiles' => $request->user()->billingProfiles()->get()]);
    }

    /** Creates a natural or legal invoice-recipient profile. */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        if ($data['is_default'] ?? false) {
            BillingProfile::query()->where('user_id', $request->user()->id)->update(['is_default' => false]);
        }
        $request->user()->billingProfiles()->create($data);

        return back()->with('success', 'پروفایل صدور فاکتور ذخیره شد.');
    }

    /** Updates an owned invoice identity without affecting historical order snapshots. */
    public function update(Request $request, BillingProfile $profile): RedirectResponse
    {
        $this->authorizeOwnership($request, $profile);
        $data = $this->validated($request);
        if ($data['is_default'] ?? false) {
            BillingProfile::query()->where('user_id', $request->user()->id)->whereKeyNot($profile->id)->update(['is_default' => false]);
        }
        $profile->update($data);

        return back()->with('success', 'پروفایل فاکتور به‌روزرسانی شد.');
    }

    /** Deletes an owned profile while historical invoices retain their snapshots. */
    public function destroy(Request $request, BillingProfile $profile): RedirectResponse
    {
        $this->authorizeOwnership($request, $profile);
        $profile->delete();

        return back()->with('success', 'پروفایل فاکتور حذف شد.');
    }

    /** Validates fields conditionally for natural versus legal invoices. */
    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['natural', 'legal'])],
            'title' => ['nullable', 'string', 'max:100'],
            'is_default' => ['nullable', 'boolean'],
            'full_name' => ['nullable', 'required_if:type,natural', 'string', 'max:190'],
            'national_code' => ['nullable', 'required_if:type,natural', 'digits:10'],
            'company_name' => ['nullable', 'required_if:type,legal', 'string', 'max:190'],
            'national_id' => ['nullable', 'required_if:type,legal', 'digits:11'],
            'economic_code' => ['nullable', 'required_if:type,legal', 'string', 'max:20'],
            'registration_number' => ['nullable', 'string', 'max:40'],
            'phone' => ['nullable', 'string', 'max:30'],
            'mobile' => ['nullable', 'regex:/^09\d{9}$/'],
            'province' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /** Rejects cross-customer profile access. */
    private function authorizeOwnership(Request $request, BillingProfile $profile): void
    {
        abort_unless((int) $profile->user_id === (int) $request->user()->id, 404);
    }
}
