<?php

namespace App\Domain\Shipping\Contracts;

use App\Domain\Order\Models\Order;

interface ShippingProvider
{
    /** Stable carrier identifier such as post, tipax, courier or pickup. */
    public function name(): string;

    /** Creates a carrier shipment/label and returns normalized provider data. */
    public function createShipment(Order $order, array $payload = []): array;

    /** Returns normalized tracking information for a carrier tracking code. */
    public function track(string $trackingCode): array;
}
