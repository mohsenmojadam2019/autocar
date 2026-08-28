<?php

namespace App\Providers;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Promotion\Services\PriceHistoryService;
use App\Support\JalaliDate;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(JalaliDate::class);
    }

    public function boot(): void
    {
        Blade::stringable(fn (CarbonInterface $date): string => app(JalaliDate::class)->format($date));

        Product::updated(fn (Product $product) => app(PriceHistoryService::class)->capture($product));
        ProductVariant::updated(fn (ProductVariant $variant) => app(PriceHistoryService::class)->capture($variant));

        Route::middleware('web')->group(base_path('routes/extensions.php'));
        Route::middleware('web')->group(base_path('routes/completeness.php'));
        Route::middleware('web')->group(base_path('routes/final-features.php'));

        Route::middleware('web')->group(function (): void {
            Route::get('/assets/app.css', fn (): Response => $this->assetResponse(resource_path('css/app.css'), 'text/css; charset=UTF-8'))->name('assets.css');
            Route::get('/assets/extensions.css', fn (): Response => $this->assetResponse(resource_path('css/extensions.css'), 'text/css; charset=UTF-8'))->name('assets.extensions.css');
            Route::get('/assets/ux.css', fn (): Response => $this->assetResponse(resource_path('css/ux.css'), 'text/css; charset=UTF-8'))->name('assets.ux.css');
            Route::get('/assets/autocar-theme.css', fn (): Response => $this->assetResponse(resource_path('css/autocar-theme.css'), 'text/css; charset=UTF-8'))->name('assets.autocar-theme.css');
            Route::get('/assets/app.js', fn (): Response => $this->assetResponse(resource_path('js/app.js'), 'application/javascript; charset=UTF-8'))->name('assets.js');
            Route::get('/assets/ux.js', fn (): Response => $this->assetResponse(resource_path('js/ux.js'), 'application/javascript; charset=UTF-8'))->name('assets.ux.js');
        });
    }

    private function assetResponse(string $path, string $contentType): Response
    {
        $content = (string) file_get_contents($path);
        if (str_ends_with($path, 'app.css')) {
            $content = str_replace(["@import 'bootstrap/dist/css/bootstrap.rtl.min.css';\n", "@import 'bootstrap-icons/font/bootstrap-icons.css';\n"], '', $content);
        }
        $etag = '"'.sha1($content).'"';
        if (request()->header('If-None-Match') === $etag) {
            return response('', 304, ['ETag' => $etag, 'Cache-Control' => 'public, max-age=86400']);
        }

        return response($content, 200, ['Content-Type' => $contentType, 'Cache-Control' => 'public, max-age=86400', 'ETag' => $etag]);
    }
}
