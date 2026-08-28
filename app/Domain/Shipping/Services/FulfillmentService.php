<?php

namespace App\Domain\Shipping\Services;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Services\OrderService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FulfillmentService
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly ShippingProviderManager $providers,
    ) {}

    /** Creates a local/provider shipment record and first immutable tracking event for an eligible order. */
    public function createShipment(Order $order, ?int $shippingMethodId, ?string $carrier, ?string $trackingCode, int $cost = 0, ?int $weightGrams = null): int
    {
        if (! in_array($order->status->value, ['paid', 'reviewing', 'sourcing', 'ready_to_ship'], true)) {
            throw new RuntimeException('سفارش در وضعیت قابل ارسال نیست.');
        }

        $method = $shippingMethodId ? DB::table('shipping_methods')->find($shippingMethodId) : null;
        $providerName = strtolower((string) ($carrier ?: $method?->code ?: 'courier'));
        $providerName = in_array($providerName, ShippingProviderManager::PROVIDERS, true) ? $providerName : 'courier';
        $providerData = $this->providers->driver($providerName)->createShipment($order, [
            'tracking_code' => $trackingCode,
            'weight_grams' => $weightGrams,
            'shipping_method' => $method?->code,
        ]);
        $resolvedTracking = $providerData['tracking_code'] ?? $trackingCode;

        return DB::transaction(function () use ($order, $shippingMethodId, $providerName, $resolvedTracking, $cost, $weightGrams, $providerData): int {
            $id = DB::table('shipments')->insertGetId([
                'order_id' => $order->id,
                'shipping_method_id' => $shippingMethodId,
                'status' => (string) ($providerData['status'] ?? 'preparing'),
                'carrier' => $providerName,
                'tracking_code' => $resolvedTracking,
                'cost' => $cost,
                'weight_grams' => $weightGrams,
                'label_data' => json_encode([
                    'order_number' => $order->number,
                    'label_url' => $providerData['label_url'] ?? null,
                    'provider' => $providerData['provider'] ?? $providerName,
                    'provider_payload' => $providerData['payload'] ?? null,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->event($id, 'preparing', null, 'مرسوله ایجاد شد.', $providerData['payload'] ?? null);
            if ($order->status !== OrderStatus::ReadyToShip && $order->status->canTransitionTo(OrderStatus::ReadyToShip)) {
                $this->orders->transition($order, OrderStatus::ReadyToShip, 'مرسوله آماده شد.');
            }

            return $id;
        });
    }

    /** Pulls current status from the configured carrier adapter and applies it to shipment/order state. */
    public function syncProviderStatus(int $shipmentId): void
    {
        $shipment = DB::table('shipments')->find($shipmentId);
        if (! $shipment || ! $shipment->tracking_code || ! in_array($shipment->carrier, ShippingProviderManager::PROVIDERS, true)) {
            throw new RuntimeException('مرسوله دارای Provider/Tracking معتبر نیست.');
        }

        $tracking = $this->providers->driver($shipment->carrier)->track($shipment->tracking_code);
        $this->updateStatus(
            $shipmentId,
            $this->normalizeStatus((string) ($tracking['status'] ?? 'in_transit')),
            $tracking['location'] ?? null,
            $tracking['description'] ?? null,
            $shipment->tracking_code,
            $tracking['payload'] ?? null,
        );
    }

    /** Appends tracking state and synchronizes shipment/order lifecycle. */
    public function updateStatus(int $shipmentId, string $status, ?string $location = null, ?string $description = null, ?string $trackingCode = null, ?array $providerPayload = null): void
    {
        DB::transaction(function () use ($shipmentId, $status, $location, $description, $trackingCode, $providerPayload): void {
            $shipment = DB::table('shipments')->where('id', $shipmentId)->lockForUpdate()->first();
            if (! $shipment) {
                throw new RuntimeException('مرسوله یافت نشد.');
            }
            $updates = ['status' => $status, 'updated_at' => now()];
            if ($trackingCode !== null) {
                $updates['tracking_code'] = $trackingCode;
            }
            if ($status === 'shipped') {
                $updates['shipped_at'] = now();
            }
            if ($status === 'delivered') {
                $updates['delivered_at'] = now();
            }
            DB::table('shipments')->where('id', $shipmentId)->update($updates);
            $this->event($shipmentId, $status, $location, $description, $providerPayload);

            $order = Order::query()->findOrFail($shipment->order_id);
            $target = match ($status) {
                'shipped', 'in_transit' => OrderStatus::Shipped,
                'delivered' => OrderStatus::Delivered,
                default => null,
            };
            if ($target && $order->status !== $target && $order->status->canTransitionTo($target)) {
                $this->orders->transition($order, $target, 'به‌روزرسانی مرسوله: '.$status);
            }
        });
    }

    private function normalizeStatus(string $status): string
    {
        return match (strtolower($status)) {
            'created', 'pending', 'preparing' => 'preparing',
            'sent', 'shipped' => 'shipped',
            'transit', 'in_transit', 'on_the_way' => 'in_transit',
            'delivered', 'completed' => 'delivered',
            'returned', 'return' => 'returned',
            'failed', 'cancelled', 'canceled' => 'failed',
            default => 'in_transit',
        };
    }

    /** Stores an immutable shipment tracking event including normalized provider payload. */
    private function event(int $shipmentId, string $status, ?string $location, ?string $description, ?array $providerPayload = null): void
    {
        DB::table('shipment_events')->insert([
            'shipment_id' => $shipmentId,
            'status' => $status,
            'location' => $location,
            'description' => $description,
            'provider_payload' => $providerPayload ? json_encode($providerPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
