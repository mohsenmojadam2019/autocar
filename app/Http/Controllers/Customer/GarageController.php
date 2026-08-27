<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Vehicle\Models\CustomerVehicle;
use App\Domain\Vehicle\Models\VehicleMake;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GarageController extends Controller
{
    /** Lists saved vehicles and indicates the currently active storefront vehicle. */
    public function index(Request $request): View
    {
        return view('customer.garage', [
            'vehicles' => $request->user()->vehicles()->with('trim.generation.model.make')->latest()->get(),
            'makes' => VehicleMake::query()->where('is_active', true)->orderBy('name')->get(),
            'activeVehicleId' => $request->session()->get('active_vehicle_id'),
        ]);
    }

    /** Saves an exact trim vehicle and optionally makes it active/default. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['vehicle_trim_id' => ['required', 'exists:vehicle_trims,id'], 'nickname' => ['nullable', 'string', 'max:60'], 'plate' => ['nullable', 'string', 'max:30'], 'is_default' => ['nullable', 'boolean']]);
        if ($data['is_default'] ?? false) {
            $request->user()->vehicles()->update(['is_default' => false]);
        }
        $vehicle = $request->user()->vehicles()->create($data);
        if ($data['is_default'] ?? false) {
            $request->session()->put('active_vehicle_id', $vehicle->id);
        }

        return back()->with('success', 'خودرو به گاراژ اضافه شد.');
    }

    /** Makes one owned garage vehicle active for fitment badges and compatible browsing. */
    public function activate(Request $request, CustomerVehicle $vehicle): RedirectResponse
    {
        abort_unless((int) $vehicle->user_id === (int) $request->user()->id, 404);
        $request->user()->vehicles()->update(['is_default' => false]);
        $vehicle->update(['is_default' => true]);
        $request->session()->put('active_vehicle_id', $vehicle->id);

        return back()->with('success', 'خودروی فعال تغییر کرد.');
    }

    /** Deletes only a vehicle owned by the current customer and clears active selection when needed. */
    public function destroy(Request $request, CustomerVehicle $vehicle): RedirectResponse
    {
        abort_unless((int) $vehicle->user_id === (int) $request->user()->id, 404);
        if ((int) $request->session()->get('active_vehicle_id') === (int) $vehicle->id) {
            $request->session()->forget('active_vehicle_id');
        }
        $vehicle->delete();

        return back()->with('success', 'خودرو حذف شد.');
    }
}
