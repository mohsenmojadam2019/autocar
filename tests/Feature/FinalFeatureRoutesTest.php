<?php

use Illuminate\Support\Facades\Route;

it('registers the final recovery, sharing and merchandising routes', function (): void {
    foreach ([
        'wishlist.shared',
        'compare.shared',
        'cart.recover',
        'admin.final-commerce.index',
        'admin.final-commerce.bundle',
        'admin.final-commerce.category-template',
        'admin.final-commerce.reorder',
        'admin.final-commerce.tag',
        'admin.final-commerce.assign-tag',
        'admin.final-commerce.suppression',
    ] as $name) {
        expect(Route::has($name))->toBeTrue("Missing route: {$name}");
    }
});
