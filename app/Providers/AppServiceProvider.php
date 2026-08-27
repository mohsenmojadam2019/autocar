<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response;

class AppServiceProvider extends ServiceProvider
{
    /** Registers application services. */
    public function register(): void
    {
        // Domain services are resolved through Laravel's container conventions.
    }

    /**
     * Registers the framework-free AutoCar asset endpoints.
     *
     * The project intentionally does not use Vite/Node. Bootstrap and Bootstrap
     * Icons are loaded directly by the Blade layouts, while AutoCar's own CSS
     * and vanilla JavaScript stay in resources and are delivered with browser
     * cache headers through these endpoints.
     */
    public function boot(): void
    {
        Route::middleware('web')->group(function (): void {
            Route::get('/assets/app.css', function (): Response {
                $css = (string) file_get_contents(resource_path('css/app.css'));
                $css = str_replace([
                    "@import 'bootstrap/dist/css/bootstrap.rtl.min.css';\n",
                    "@import 'bootstrap-icons/font/bootstrap-icons.css';\n",
                ], '', $css);

                return response($css, 200, [
                    'Content-Type' => 'text/css; charset=UTF-8',
                    'Cache-Control' => 'public, max-age=86400',
                ]);
            })->name('assets.css');

            Route::get('/assets/app.js', function (): Response {
                $javascript = (string) file_get_contents(resource_path('js/app.js'));
                $javascript = preg_replace("/^import 'bootstrap';\\R^import 'bootstrap-icons\\/font\\/bootstrap-icons\\.css';\\R*/m", '', $javascript) ?? $javascript;

                return response($javascript, 200, [
                    'Content-Type' => 'application/javascript; charset=UTF-8',
                    'Cache-Control' => 'public, max-age=86400',
                ]);
            })->name('assets.js');
        });
    }
}
