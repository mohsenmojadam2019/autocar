<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Search\Services\ProductSearchService;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    /** Lists a category with server-side brand/price filters and stable pagination. */
    public function category(Request $request, string $slug): View
    {
        $category = Category::query()->visible()->where('slug',$slug)->firstOrFail();
        $query = $category->products()->published()->with(['brand','media']);
        $this->applyFilters($query,$request);
        return view('storefront.catalog.index', ['title'=>$category->name,'category'=>$category,'products'=>$query->paginate(24)->withQueryString(),'brands'=>Brand::query()->visible()->orderBy('name')->get(),'breadcrumbs'=>$category->breadcrumb()]);
    }

    /** Renders search results using the central SKU/OEM/Persian search service. */
    public function search(Request $request, ProductSearchService $search): View
    {
        $data=$request->validate(['q'=>['nullable','string','max:100'],'brand_id'=>['nullable','integer'],'category_id'=>['nullable','integer']]);
        $term=trim($data['q']??'');
        $products=$term!=='' ? $search->search($term,$data) : Product::query()->published()->with(['brand','media'])->latest('published_at')->paginate(24);
        return view('storefront.catalog.index',['title'=>$term!==''?'نتایج جست‌وجوی «'.$term.'»':'همه قطعات','products'=>$products,'brands'=>Brand::query()->visible()->orderBy('name')->get(),'breadcrumbs'=>[],'category'=>null]);
    }

    /** Shows one published product with media/specification/fitment and related catalog data. */
    public function product(string $slug): View
    {
        $product=Product::query()->published()->where('slug',$slug)->with(['brand','media','categories','variants','attributeValues.attribute','fitments'])->firstOrFail();
        $related=Product::query()->published()->whereKeyNot($product->id)->when($product->brand_id,fn($q)=>$q->where('brand_id',$product->brand_id))->with('media')->limit(4)->get();
        return view('storefront.product.show',compact('product','related'));
    }

    /** Applies safe catalog filters without allowing arbitrary client-supplied columns. */
    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->integer('brand_id'),fn(Builder $q,int $id)=>$q->where('brand_id',$id))
            ->when($request->integer('min_price'),fn(Builder $q,int $v)=>$q->where('sale_price','>=',$v))
            ->when($request->integer('max_price'),fn(Builder $q,int $v)=>$q->where('sale_price','<=',$v));
        match($request->string('sort')->toString()) { 'price_asc'=>$query->orderBy('sale_price'), 'price_desc'=>$query->orderByDesc('sale_price'), 'oldest'=>$query->oldest(), default=>$query->latest('published_at') };
    }
}
