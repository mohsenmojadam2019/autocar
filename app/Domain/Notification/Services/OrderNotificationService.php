<?php

namespace App\Domain\Notification\Services;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Sms\Services\SmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class OrderNotificationService
{
    public function __construct(private readonly SmsService $sms) {}

    /** Delivers order status updates through channels allowed by the customer's preferences. */
    public function statusChanged(Order $order, OrderStatus $status): void
    {
        $order->loadMissing('user');
        $user = $order->user;
        if (! $user) {
            return;
        }
        $event = 'order.'.$status->value;
        $preference = DB::table('notification_preferences')->where('user_id', $user->id)->where('event', $event)->first();
        $smsEnabled = $preference ? (bool) $preference->sms : true;
        $emailEnabled = $preference ? (bool) $preference->email : false;
        $databaseEnabled = $preference ? (bool) $preference->database : true;
        $message = $this->message($order, $status);

        if ($databaseEnabled) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => 'order_status',
                'notifiable_type' => $user::class,
                'notifiable_id' => $user->id,
                'data' => json_encode(['event' => $event, 'order_number' => $order->number, 'status' => $status->value, 'message' => $message], JSON_UNESCAPED_UNICODE),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        if ($smsEnabled && $user->mobile) {
            $this->sms->queue($user->mobile, $message, $user->id, $event);
        }
        if ($emailEnabled && $user->email) {
            try {
                Mail::raw($message, fn ($mail) => $mail->to($user->email)->subject('وضعیت سفارش '.$order->number));
            } catch (Throwable) {
                // Database/SMS delivery remains durable even when optional mail transport is unavailable.
            }
        }
    }

    /** Maps order states to concise transactional Persian messages. */
    private function message(Order $order, OrderStatus $status): string
    {
        $label = match ($status) {
            OrderStatus::Paid => 'پرداخت تأیید شد',
            OrderStatus::Reviewing => 'در حال بررسی',
            OrderStatus::Sourcing => 'در حال تأمین کالا',
            OrderStatus::ReadyToShip => 'آماده ارسال',
            OrderStatus::Shipped => 'ارسال شد',
            OrderStatus::Delivered => 'تحویل شد',
            OrderStatus::Cancelled => 'لغو شد',
            OrderStatus::Returned => 'مرجوع شد',
            OrderStatus::Refunded => 'بازپرداخت شد',
            default => $status->value,
        };

        return 'اتوکار | سفارش '.$order->number.': '.$label;
    }
}
