<?php

namespace Tests\Feature;

use Tests\TestCase;

class AssetDeliveryTest extends TestCase
{
    /** Ensures the AutoCar stylesheet is served without npm/Vite import directives. */
    public function test_stylesheet_is_delivered_without_vite_or_node_imports(): void
    {
        $response = $this->get('/assets/app.css');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/css; charset=UTF-8');
        $response->assertDontSee("@import 'bootstrap/dist/css/bootstrap.rtl.min.css'", false);
        $response->assertDontSee("@import 'bootstrap-icons/font/bootstrap-icons.css'", false);
    }

    /** Ensures vanilla AutoCar JavaScript reaches browsers without bare package imports. */
    public function test_javascript_is_delivered_without_vite_or_node_imports(): void
    {
        $response = $this->get('/assets/app.js');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/javascript; charset=UTF-8');
        $response->assertDontSee("import 'bootstrap'", false);
        $response->assertDontSee("import 'bootstrap-icons/font/bootstrap-icons.css'", false);
    }
}
