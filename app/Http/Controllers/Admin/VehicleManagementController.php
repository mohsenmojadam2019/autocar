<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Models\Product;
use App\Domain\Vehicle\Enums\FitmentStatus;
use App\Domain\Vehicle\Models\ProductFitment;
use App\Domain\Vehicle\Models\VehicleEngine;
use App\Domain\Vehicle\Models\VehicleGeneration;
use App\Domain\Vehicle\Models\VehicleMake;
use App\Domain\Vehicle\Models\VehicleModel;
use App\Domain\Vehicle\Models\VehicleTrim;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VehicleManagementController extends Controller
{
    /** Shows normalized vehicle master data and recent fitment rules. */
    public function index(): View
    {
        return view('admin.vehicles.index', [
            'makes' => VehicleMake::query()->with('models.generations.trims')->orderBy('name')->get(),
            'engines' => VehicleEngine::query()->latest()->limit(100)->get(),
            'fitments' => ProductFitment::query()->with(['product', 'make', 'model', 'generation', 'trim', 'engine'])->latest()->limit(50)->get(),
            'products' => Product::query()->orderBy('name')->limit(250)->get(['slug', 'name', 'sku']),
        ]);
    }

    /** Creates a top-level vehicle manufacturer. */
    public function storeMake(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'name_en' => ['nullable', 'string', 'max:100'], 'slug' => ['nullable', 'string', 'max:120', 'unique:vehicle_makes,slug']]);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name_en'] ?: $data['name']);
        VehicleMake::query()->create($data + ['is_active' => true]);

        return back()->with('success', 'برند خودرو ایجاد شد.');
    }

    /** Creates a vehicle model under an existing make. */
    public function storeModel(Request $request): RedirectResponse
    {
        $data = $request->validate(['vehicle_make_id' => ['required', 'exists:vehicle_makes,id'], 'name' => ['required', 'string', 'max:100'], 'name_en' => ['nullable', 'string', 'max:100'], 'slug' => ['nullable', 'string', 'max:120']]);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name_en'] ?: $data['name']);
        VehicleModel::query()->create($data + ['is_active' => true]);

        return back()->with('success', 'مدل خودرو ایجاد شد.');
    }

    /** Creates a production generation and validates its optional year range. */
    public function storeGeneration(Request $request): RedirectResponse
    {
        $data = $request->validate(['vehicle_model_id' => ['required', 'exists:vehicle_models,id'], 'name' => ['required', 'string', 'max:100'], 'from_year' => ['nullable', 'integer', 'min:1900', 'max:2200'], 'to_year' => ['nullable', 'integer', 'gte:from_year', 'max:2200'], 'body_type' => ['nullable', 'string', 'max:50']]);
        VehicleGeneration::query()->create($data);

        return back()->with('success', 'نسل خودرو ایجاد شد.');
    }

    /** Creates a reusable engine definition. */
    public function storeEngine(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'code' => ['nullable', 'string', 'max:50'], 'displacement_cc' => ['nullable', 'integer', 'min:100', 'max:15000'], 'fuel_type' => ['nullable', 'string', 'max:24'], 'power_hp' => ['nullable', 'integer', 'min:1', 'max:3000']]);
        VehicleEngine::query()->create($data);

        return back()->with('success', 'موتور خودرو ثبت شد.');
    }

    /** Creates an exact year/trim configuration used by the fitment resolver. */
    public function storeTrim(Request $request): RedirectResponse
    {
        $data = $request->validate(['vehicle_generation_id' => ['required', 'exists:vehicle_generations,id'], 'vehicle_engine_id' => ['nullable', 'exists:vehicle_engines,id'], 'name' => ['required', 'string', 'max:100'], 'year' => ['required', 'integer', 'min:1900', 'max:2200'], 'transmission' => ['nullable', 'string', 'max:32'], 'drivetrain' => ['nullable', 'string', 'max:16'], 'market' => ['nullable', 'string', 'max:32']]);
        VehicleTrim::query()->create($data + ['is_active' => true]);

        return back()->with('success', 'تیپ/سال خودرو ایجاد شد.');
    }

    /** Stores a fitment rule with product resolved strictly by product slug. */
    public function storeFitment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_slug' => ['required', 'exists:products,slug'],
            'product_variant_id' => ['nullable', 'exists:product_variants,id'],
            'vehicle_make_id' => ['nullable', 'exists:vehicle_makes,id'],
            'vehicle_model_id' => ['nullable', 'exists:vehicle_models,id'],
            'vehicle_generation_id' => ['nullable', 'exists:vehicle_generations,id'],
            'vehicle_trim_id' => ['nullable', 'exists:vehicle_trims,id'],
            'vehicle_engine_id' => ['nullable', 'exists:vehicle_engines,id'],
            'from_year' => ['nullable', 'integer', 'min:1900', 'max:2200'],
            'to_year' => ['nullable', 'integer', 'gte:from_year', 'max:2200'],
            'status' => ['required', Rule::enum(FitmentStatus::class)],
            'is_exclusion' => ['nullable', 'boolean'],
            'confidence' => ['required', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $product = Product::query()->where('slug', $data['product_slug'])->firstOrFail();
        unset($data['product_slug']);
        ProductFitment::query()->create($data + ['product_id' => $product->id]);

        return back()->with('success', 'قانون سازگاری ثبت شد.');
    }

    /** Deletes one fitment rule without affecting catalog or vehicle master data. */
    public function destroyFitment(ProductFitment $fitment): RedirectResponse
    {
        $fitment->delete();

        return back()->with('success', 'قانون سازگاری حذف شد.');
    }
}
