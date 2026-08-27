<?php

namespace App\Domain\Sms\Jobs;

use App\Domain\Sms\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 600];

    public function __construct(
        public readonly string $mobile,
        public readonly string $body,
        public readonly ?int $userId = null,
        public readonly ?string $templateKey = null,
        public readonly ?string $providerName = null,
    ) {}

    /** Sends one queued message through the central logged SMS service. */
    public function handle(SmsService $sms): void
    {
        $sms->send($this->mobile, $this->body, $this->userId, $this->templateKey, $this->providerName);
    }
}
