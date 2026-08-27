<?php

namespace App\Http\Controllers\Admin;

use App\Domain\CRM\Services\CustomerMetricsService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CustomerController extends Controller
{
    /** Lists customers with CRM metrics, search and customer-group filtering. */
    public function index(Request $request): View
    {
        $customers = User::query()->leftJoin('customer_profiles', 'customer_profiles.user_id', '=', 'users.id')
            ->select('users.*', 'customer_profiles.customer_group', 'customer_profiles.lifetime_value', 'customer_profiles.orders_count')
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($search) => $search->where('users.name', 'like', '%'.$request->q.'%')->orWhere('users.mobile', 'like', '%'.$request->q.'%')->orWhere('users.email', 'like', '%'.$request->q.'%')))
            ->when($request->filled('group'), fn ($query) => $query->where('customer_profiles.customer_group', $request->group))
            ->orderByDesc('users.id')->paginate(30)->withQueryString();

        return view('admin.customers.index', compact('customers'));
    }

    /** Shows a 360-degree customer view with orders, vehicles, notes, tags and consent state. */
    public function show(User $customer, CustomerMetricsService $metrics): View
    {
        $metrics->rebuild($customer->id);
        return view('admin.customers.show', [
            'customer' => $customer->load(['vehicles.trim.generation.model.make', 'addresses']),
            'orders' => DB::table('orders')->where('user_id', $customer->id)->latest()->limit(20)->get(),
            'profile' => DB::table('customer_profiles')->where('user_id', $customer->id)->first(),
            'notes' => DB::table('customer_notes')->where('user_id', $customer->id)->latest()->get(),
            'consents' => DB::table('marketing_consents')->where('user_id', $customer->id)->get(),
        ]);
    }

    /** Adds an internal CRM note with the authenticated staff member as author. */
    public function note(Request $request, User $customer): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:3000'], 'is_pinned' => ['nullable', 'boolean']]);
        DB::table('customer_notes')->insert(['user_id' => $customer->id, 'author_id' => $request->user()->id, 'note' => $data['note'], 'is_pinned' => $data['is_pinned'] ?? false, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'یادداشت CRM ثبت شد.');
    }
}
