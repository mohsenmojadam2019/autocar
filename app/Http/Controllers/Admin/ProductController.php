<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Services\ProductCloneService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    /** Lists products server-side with bounded search and status filters. */ public function index(Request $request): View { $q=Product::query()->with('brand')->when($request->filled('q'),fn($b)=>$b->where(fn($x)=>$x->where('name','like','%'.$request->q.'%')->orWhere('sku','like','%'.$request->q.'%')->orWhere('oem_code','like','%'.$request->q.'%')))->when($request->filled('status'),fn($b)=>$b->where('status',$request->status))->latest(); return view('admin.products.index',['products'=>$q->paginate(30)->withQueryString()]); }
    /** Shows the create form with reusable taxonomy data. */ public function create(): View { return view('admin.products.form',['product'=>new Product(),'brands'=>Brand::query()->orderBy('name')->get(),'categories'=>Category::query()->orderBy('name')->get()]); }
    /** Stores a product with authoritative SKU/slug validation and category assignments. */ public function store(Request $request): RedirectResponse { $data=$this->validated($request); $categories=$data['categories']??[]; unset($data['categories']); $data['slug']=$data['slug']?:Str::slug($data['name']); $product=Product::query()->create($data); $product->categories()->sync($categories); return redirect()->route('admin.products.edit',$product)->with('success','محصول ایجاد شد.'); }
    /** Shows one product edit form. */ public function edit(Product $product): View { $product->load('categories'); return view('admin.products.form',['product'=>$product,'brands'=>Brand::query()->orderBy('name')->get(),'categories'=>Category::query()->orderBy('name')->get()]); }
    /** Updates the product and resynchronizes category membership. */ public function update(Request $request,Product $product): RedirectResponse { $data=$this->validated($request,$product->id); $categories=$data['categories']??[]; unset($data['categories']); $product->update($data); $product->categories()->sync($categories); return back()->with('success','محصول ذخیره شد.'); }
    /** Deep-clones a complete product into draft state. */ public function duplicate(Product $product,ProductCloneService $cloner): RedirectResponse { $copy=$cloner->clone($product); return redirect()->route('admin.products.edit',$copy)->with('success','کپی محصول ساخته شد.'); }
    /** Centralizes product validation shared by create and update operations. */ private function validated(Request $request,?int $id=null): array { return $request->validate(['name'=>['required','string','max:190'],'name_en'=>['nullable','string','max:190'],'slug'=>['nullable','string','max:190','unique:products,slug,'.$id],'sku'=>['required','string','max:100','unique:products,sku,'.$id],'oem_code'=>['nullable','string','max:100'],'manufacturer_code'=>['nullable','string','max:100'],'brand_id'=>['nullable','exists:brands,id'],'authenticity'=>['required','in:original,oem,imported,company,economic'],'status'=>['required','in:draft,active,inactive,archived'],'summary'=>['nullable','string','max:1000'],'description'=>['nullable','string'],'warranty'=>['nullable','string','max:190'],'return_days'=>['required','integer','min:0','max:365'],'sale_price'=>['required','integer','min:0'],'purchase_price'=>['nullable','integer','min:0'],'compare_at_price'=>['nullable','integer','min:0'],'wholesale_price'=>['nullable','integer','min:0'],'categories'=>['nullable','array'],'categories.*'=>['integer','exists:categories,id']]); }
}
