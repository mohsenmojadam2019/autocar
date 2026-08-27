<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Services\CategoryTreeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    /** Shows the complete unlimited-depth category tree. */ public function index(CategoryTreeService $service): View { return view('admin.categories.index',['tree'=>$service->tree()]); }
    /** Creates one category and calculates its depth from the selected parent. */ public function store(Request $request): RedirectResponse { $data=$request->validate(['name'=>['required','string','max:190'],'slug'=>['nullable','string','max:190','unique:categories,slug'],'parent_id'=>['nullable','exists:categories,id'],'position'=>['nullable','integer','min:0'],'is_active'=>['nullable','boolean']]); $parent=isset($data['parent_id'])?Category::find($data['parent_id']):null; $data['slug']=$data['slug']?:Str::slug($data['name']); $data['depth']=$parent?$parent->depth+1:0; Category::query()->create($data); return back()->with('success','دسته‌بندی ایجاد شد.'); }
    /** Moves a category through the circular-reference-safe domain service. */ public function move(Request $request,Category $category,CategoryTreeService $service): RedirectResponse { $data=$request->validate(['parent_id'=>['nullable','exists:categories,id'],'position'=>['required','integer','min:0']]); $service->move($category,isset($data['parent_id'])?Category::find($data['parent_id']):null,$data['position']); return back()->with('success','جایگاه دسته‌بندی تغییر کرد.'); }
}
