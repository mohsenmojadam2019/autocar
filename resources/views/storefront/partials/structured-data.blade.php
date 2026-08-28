@if(isset($product) && $product instanceof \App\Domain\Catalog\Models\Product)
@php
    $primaryMedia = $product->relationLoaded('media') ? $product->media->first() : $product->media()->orderBy('position')->first();
    $schemaPrice = isset($price) && is_array($price) ? (int) ($price['final_price'] ?? $product->sale_price) : (int) $product->sale_price;
    $productSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product->name,
        'sku' => $product->sku,
        'mpn' => $product->manufacturer_code ?: $product->oem_code,
        'description' => $product->summary ?: $product->meta_description,
        'url' => route('product.show', $product),
        'image' => $primaryMedia ? [asset('storage/'.$primaryMedia->path)] : [],
        'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
        'offers' => [
            '@type' => 'Offer',
            'priceCurrency' => 'IRR',
            'price' => $schemaPrice,
            'url' => route('product.show', $product),
            'availability' => 'https://schema.org/InStock',
        ],
    ];
    $breadcrumbs = [['@type' => 'ListItem', 'position' => 1, 'name' => 'خانه', 'item' => route('home')]];
    foreach (($product->categories ?? collect())->take(1) as $category) {
        $breadcrumbs[] = ['@type' => 'ListItem', 'position' => count($breadcrumbs) + 1, 'name' => $category->name, 'item' => route('category.show', $category)];
    }
    $breadcrumbs[] = ['@type' => 'ListItem', 'position' => count($breadcrumbs) + 1, 'name' => $product->name, 'item' => route('product.show', $product)];
    $breadcrumbSchema = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $breadcrumbs];
@endphp
<link rel="canonical" href="{{ route('product.show', $product) }}">
<script type="application/ld+json">{!! json_encode(array_filter($productSchema, fn($value) => $value !== null && $value !== []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endif
