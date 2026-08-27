<?php

namespace App\Domain\Payment\Contracts;

use App\Domain\Order\Models\Order;
use App\Domain\Payment\DTOs\PaymentRequest;
use App\Domain\Payment\DTOs\PaymentVerification;
use App\Domain\Payment\Models\PaymentTransaction;

interface PaymentGateway
{
    /** Returns the stable internal gateway identifier. */
    public function name(): string;

    /** Requests a remote authority for the exact amount still payable after wallet usage. */
    public function request(Order $order, string $callbackUrl, ?int $amount = null): PaymentRequest;

    /** Verifies callback data against the provider before any order is marked paid. */
    public function verify(PaymentTransaction $transaction, array $callback): PaymentVerification;

    /** Attempts a provider-side refund when the gateway supports it. */
    public function refund(PaymentTransaction $transaction, int $amount): PaymentVerification;
}
