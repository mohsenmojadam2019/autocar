@extends('layouts.admin')

@section('title', 'مدیریت کامل '.$product->name.' | اتوکار')
@section('page_title', 'Workbench محصول')

@section('content')
<div class="admin-heading">
    <div><h1>{{ $product->name }}</h1><p>تصاویر، SKU/تنوع، مشخصات فنی و محصولات مشابه/مکمل/جایگزین/Upsell</p></div>
    <div class="d-flex gap-2"><a class="btn btn-light" href="{{ route('admin.products.edit', $product) }}">اطلاعات پایه</a><a class="btn btn-outline-primary" target="_blank" href="{{ route('product.show', $product) }}">پیش‌نمایش</a></div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="admin-card">
            <h4>تصاویر محصول</h4>
            <form class="row g-2 align-items-end mb-4" method="post" enctype="multipart/form-data" action="{{ route('admin.products.workbench.media.store', $product) }}">
                @csrf
                <div class="col-md-5"><label>تصویر</label><input class="form-control" type="file" name="image" accept="image/jpeg,image/png,image/webp" required></div>
                <div class="col-md-4"><label>Alt</label><input class="form-control" name="alt" placeholder="توضیح تصویر برای SEO"></div>
                <div class="col-md-2"><label class="d-flex gap-2 align-items-center"><input type="checkbox" name="is_primary" value="1"> تصویر اصلی</label></div>
                <div class="col-md-1"><button class="btn btn-primary w-100"><i class="bi bi-upload"></i></button></div>
            </form>
            @if($product->media->isNotEmpty())
                <form method="post" action="{{ route('admin.products.workbench.media.update', $product) }}">@csrf @method('put')
                    <div class="row g-3">
                        @foreach($product->media as $media)
                            <div class="col-md-6 col-xl-4"><div class="border rounded p-2 h-100"><img class="w-100 rounded mb-2" style="height:150px;object-fit:contain;background:#f5f6f7" src="{{ asset('storage/'.$media->path) }}" alt=""><input type="hidden" name="media[{{ $loop->index }}][id]" value="{{ $media->id }}"><label>Alt</label><input class="form-control mb-2" name="media[{{ $loop->index }}][alt]" value="{{ $media->alt }}"><div class="d-flex gap-2 align-items-center"><label class="flex-grow-1">ترتیب <input class="form-control" type="number" min="0" name="media[{{ $loop->index }}][position]" value="{{ $media->position }}"></label><label><input type="radio" name="primary_media_id" value="{{ $media->id }}" @checked($media->is_primary)> اصلی</label></div><button class="btn btn-sm btn-outline-danger mt-2" type="submit" form="delete-media-{{ $media->id }}">حذف تصویر</button></div></div>
                        @endforeach
                    </div>
                    <button class="btn btn-outline-primary mt-3">ذخیره ترتیب تصاویر</button>
                </form>
                @foreach($product->media as $media)<form id="delete-media-{{ $media->id }}" method="post" action="{{ route('admin.products.workbench.media.destroy', [$product, $media]) }}">@csrf @method('delete')</form>@endforeach
            @else
                <div class="text-muted">هنوز تصویری ثبت نشده است.</div>
            @endif
        </div>
    </div>

    <div class="col-xl-7">
        <div class="admin-card h-100">
            <h4>تنوع‌ها / SKU</h4>
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>نام</th><th>SKU</th><th>قیمت فروش</th><th>قبل تخفیف</th><th>فعال</th><th></th></tr></thead><tbody>
            @foreach($product->variants as $variant)
                <tr><form method="post" action="{{ route('admin.products.workbench.variants.update', [$product, $variant]) }}">@csrf @method('put')<td><input class="form-control" name="name" value="{{ $variant->name }}"></td><td><input class="form-control" name="sku" value="{{ $variant->sku }}" required></td><td><input class="form-control" type="number" name="sale_price" min="0" value="{{ $variant->sale_price }}" required></td><td><input class="form-control" type="number" name="compare_at_price" min="0" value="{{ $variant->compare_at_price }}"></td><td><input type="checkbox" name="is_active" value="1" @checked($variant->is_active)></td><td class="text-nowrap"><button class="btn btn-sm btn-outline-primary">ذخیره</button><button class="btn btn-sm btn-outline-danger" type="submit" form="delete-variant-{{ $variant->id }}">حذف</button></td></form></tr>
            @endforeach
            </tbody></table></div>
            @foreach($product->variants as $variant)<form id="delete-variant-{{ $variant->id }}" method="post" action="{{ route('admin.products.workbench.variants.destroy', [$product, $variant]) }}">@csrf @method('delete')</form>@endforeach
            <hr>
            <form class="row g-2" method="post" action="{{ route('admin.products.workbench.variants.store', $product) }}">@csrf<div class="col-md-4"><input class="form-control" name="name" placeholder="نام تنوع"></div><div class="col-md-4"><input class="form-control" name="sku" required placeholder="SKU یکتا"></div><div class="col-md-4"><input class="form-control" type="number" name="sale_price" min="0" required placeholder="قیمت فروش"></div><div class="col-md-4"><input class="form-control" name="barcode" placeholder="بارکد"></div><div class="col-md-4"><input class="form-control" type="number" name="purchase_price" min="0" placeholder="قیمت خرید"></div><div class="col-md-4"><input class="form-control" type="number" name="compare_at_price" min="0" placeholder="قیمت قبل تخفیف"></div><div class="col-12"><button class="btn btn-primary">افزودن تنوع</button></div></form>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="admin-card h-100">
            <h4>مشخصات فنی</h4>
            <form method="post" action="{{ route('admin.products.workbench.specifications', $product) }}">@csrf @method('put')
                @foreach($attributes as $attribute)
                    @php($current = $product->attributeValues->firstWhere('attribute_id', $attribute->id))
                    <div class="mb-3"><input type="hidden" name="specifications[{{ $loop->index }}][attribute_code]" value="{{ $attribute->code }}"><label>{{ $attribute->name }} @if($attribute->unit)<small>({{ $attribute->unit }})</small>@endif</label>
                    @if($attribute->options->isNotEmpty())
                        <select class="form-select" name="specifications[{{ $loop->index }}][option_slug]"><option value="">انتخاب نشده</option>@foreach($attribute->options as $option)<option value="{{ $option->slug }}" @selected($current?->attribute_option_id === $option->id)>{{ $option->value }}</option>@endforeach</select>
                    @else
                        <input class="form-control" name="specifications[{{ $loop->index }}][value]" value="{{ $current?->value }}">
                    @endif</div>
                @endforeach
                <button class="btn btn-primary w-100">ذخیره مشخصات</button>
            </form>
        </div>
    </div>

    <div class="col-12">
        <div class="admin-card">
            <h4>Merchandising و محصولات پیشنهادی</h4>
            <p class="text-muted">همه انتخاب‌ها با Slug محصول ذخیره و مدیریت می‌شوند. ترتیب انتخاب، ترتیب نمایش است.</p>
            <div class="merch-admin-grid">
                @foreach(['related'=>'محصولات مشابه','complementary'=>'محصولات مکمل','alternative'=>'جایگزین‌ها','upsell'=>'Upsell / نسخه بالاتر'] as $type=>$label)
                    @php($selectedSlugs = $product->relations->where('type', $type)->sortBy('position')->pluck('relatedProduct.slug')->filter()->all())
                    <form class="border rounded p-3" method="post" action="{{ route('admin.products.workbench.relations', $product) }}">@csrf @method('put')<input type="hidden" name="type" value="{{ $type }}"><h5>{{ $label }}</h5><select class="form-select" name="product_slugs[]" multiple size="10">@foreach($candidateProducts as $candidate)<option value="{{ $candidate->slug }}" @selected(in_array($candidate->slug, $selectedSlugs, true))>{{ $candidate->name }} — {{ $candidate->sku }} — {{ $candidate->slug }}</option>@endforeach</select><button class="btn btn-outline-primary mt-2 w-100">ذخیره {{ $label }}</button></form>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
