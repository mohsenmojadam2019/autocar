<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Vehicle\Models\CustomerVehicle;
use App\Domain\Vehicle\Models\VehicleMake;
use App\Domain\Vehicle\Models\VehicleTrim;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GarageController extends Controller
{
    /** Lists saved vehicles and vehicle makes for the add-vehicle wizard. */ public function index(Request $request): View { return view('customer.garage',['vehicles'=>$request->user()->vehicles()->with('trim.generation.model.make')->latest()->get(),'makes'=>VehicleMake::query()->where('is_active',true)->orderBy('name')->get()]); }
    /** Saves an exact trim/year vehicle and can atomically make it the customer's default. */ public function store(Request $request): RedirectResponse { $data=$request->validate(['vehicle_trim_id'=>['required','exists:vehicle_trims,id'],'nickname'=>['nullable','string','max:60'],'plate'=>['nullable','string','max:30'],'is_default'=>['nullable','boolean']]); if($data['is_default']??false)$request->user()->vehicles()->update(['is_default'=>false]); $request->user()->vehicles()->create($data); return back()->with('success','خودرو به گاراژ اضافه شد.'); }
    /** Deletes only a vehicle owned by the current customer. */ public function destroy(Request $request,CustomerVehicle $vehicle): RedirectResponse { abort_unless((int)$vehicle->user_id===(int)$request->user()->id,403); $vehicle->delete(); return back()->with('success','خودرو حذف شد.'); }
}
