<?php

namespace App\Domain\Sms\Services;

use App\Domain\Sms\Contracts\SmsProvider;
use App\Domain\Sms\Jobs\SendSmsJob;
use App\Domain\Sms\Providers\ConfigurableSmsProvider;
use App\Domain\Sms\Providers\KavenegarProvider;
use App\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SmsService
{
    public const CONFIGURABLE_PROVIDERS = ['smsir', 'ippanel', 'faraz', 'ghasedak', 'melipayamak', 'modirpayamak'];

    public function __construct(private readonly SettingsRepository $settings) {}

    /** Sends and logs one transactional SMS synchronously for worker execution. */
    public function send(string $mobile, string $body, ?int $userId = null, ?string $templateKey = null, ?string $providerName = null): int
    {
        $provider = $this->provider($providerName);
        $id = (int) DB::table('sms_messages')->insertGetId([
            'user_id' => $userId,
            'provider' => $provider->name(),
            'mobile' => $mobile,
            'template_key' => $templateKey,
            'body' => $body,
            'status' => 'sending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $result = $provider->send($mobile, $body);
            DB::table('sms_messages')->where('id', $id)->update([
                'status' => 'sent',
                'provider_message_id' => $result['id'] ?? null,
                'meta' => json_encode($result['response'] ?? [], JSON_UNESCAPED_UNICODE),
                'sent_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            DB::table('sms_messages')->where('id', $id)->update(['status' => 'failed', 'error' => $exception->getMessage(), 'updated_at' => now()]);
            throw $exception;
        }

        return $id;
    }

    /** Dispatches SMS delivery to the queue used by transactional and campaign workloads. */
    public function queue(string $mobile, string $body, ?int $userId = null, ?string $templateKey = null, ?string $providerName = null): void
    {
        SendSmsJob::dispatch($mobile, $body, $userId, $templateKey, $providerName)->onQueue('sms');
    }

    /** Synchronizes provider delivery state into the durable SMS log. */
    public function syncDelivery(int $messageId): array
    {
        $message = DB::table('sms_messages')->where('id', $messageId)->first();
        if (! $message || ! $message->provider_message_id) {
            throw new RuntimeException('پیامک یا شناسه Provider موجود نیست.');
        }
        $status = $this->provider($message->provider)->deliveryStatus((string) $message->provider_message_id);
        DB::table('sms_messages')->where('id', $messageId)->update([
            'status' => (string) ($status['status'] ?? $message->status),
            'meta' => json_encode($status['response'] ?? [], JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        return $status;
    }

    /** Resolves Kavenegar or one of the configurable SMS provider adapters. */
    public function provider(?string $providerName = null): SmsProvider
    {
        $name = $providerName ?: (string) $this->settings->get('sms.default_provider', 'kavenegar');
        if ($name === 'kavenegar') {
            return app(KavenegarProvider::class);
        }
        if (in_array($name, self::CONFIGURABLE_PROVIDERS, true)) {
            return new ConfigurableSmsProvider($this->settings, $name);
        }

        throw new RuntimeException('درگاه پیامک پشتیبانی نمی‌شود.');
    }
}
