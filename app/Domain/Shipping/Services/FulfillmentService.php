<?php

namespace App\Domain\Shipping\Services;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Services\OrderService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FulfillmentService
{
    public function __construct(private readonly OrderService $orders) {}

    /** Creates the shipment record and a first tracking event for a paid/ready order. */
    public function createShipment(Order $order, ?int $shippingMethodId, ?string $carrier, ?string $trackingCode, int $cost = 0, ?int $weightGrams = null): int
    {
        if (! in_array($order->status->value, ['paid', 'reviewing', 'sourcing', 'ready_to_ship'], true)) {
            throw new RuntimeException('سفارش در وضعیت قابل ارسال نیست.');
        }

        return DB::transaction(function () use ($order, $shippingMethodId, $carrier, $trackingCode, $cost, $weightGrams): int {
            $id = DB::table('shipments')->insertGetId([
                'order_id' => $order->id,
                'shipping_method_id' => $shippingMethodId,
                'status' => 'preparing',
                'carrier' => $carrier,
                'tracking_code' => $trackingCode,
                'cost' => $cost,
                'weight_grams' => $weightGrams,
                'label_data' => json_encode(['order_number' => $order->number], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->event($id, 'preparing', null, 'مرسوله ایجاد شد.');
            if ($order->status !== OrderStatus::ReadyToShip && $order->status->canTransitionTo(OrderStatus::ReadyToShip)) {
                $this->orders->transition($order, OrderStatus::ReadyToShip, 'مرسوله آماده شد.');
            }

            return $id;
        });
    }

    /** Appends tracking state and synchronizes shipment/order lifecycle. */
    public function updateStatus(int $shipmentId, string $status, ?string $location = null, ?string $description = null, ?string $trackingCode = null): void
    {
        DB::transaction(function () use ($shipmentId, $status, $location, $description, $trackingCode): void {
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
            $this->event($shipmentId, $status, $location, $description);

            $order = Order::query()->findOrFail($shipment->order_id);
            $target = match ($status) {
                'shipped' => OrderStatus::Shipped,
                'delivered' => OrderStatus::Delivered,
                default => null,
            };
            if ($target && $order->status !== $target && $order->status->canTransitionTo($target)) {
                $this->orders->transition($order, $target, 'به‌روزرسانی مرسوله: '.$status);
            }
        });
    }

    /** Stores an immutable shipment tracking event. */
    private function event(int $shipmentId, string $status, ?string $location, ?string $description): void
    {
        DB::table('shipment_events')->insert([
            'shipment_id' => $shipmentId,
            'status' => $status,
            'location' => $location,
            'description' => $description,
            'provider_payload' => null,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
