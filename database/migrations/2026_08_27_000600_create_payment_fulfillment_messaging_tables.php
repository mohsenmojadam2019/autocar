<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Builds payment, wallet, shipping, invoice, RMA, SMS and notification infrastructure. */
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway', 32)->index();
            $table->string('status', 24)->default('initiated')->index();
            $table->string('idempotency_key')->unique();
            $table->string('authority')->nullable()->index();
            $table->string('reference_id')->nullable()->index();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 8)->default('IRR');
            $table->json('request_payload')->nullable();
            $table->json('callback_payload')->nullable();
            $table->json('verify_payload')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->bigInteger('balance')->default(0);
            $table->timestamps();
        });

        Schema::create('wallet_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['reference_type','reference_id']);
        });

        Schema::create('shipping_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type', 24)->default('flat');
            $table->unsignedBigInteger('base_price')->default(0);
            $table->unsignedBigInteger('price_per_kg')->default(0);
            $table->unsignedBigInteger('free_over')->nullable();
            $table->unsignedSmallInteger('min_days')->nullable();
            $table->unsignedSmallInteger('max_days')->nullable();
            $table->json('zones')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipping_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->string('carrier')->nullable();
            $table->string('tracking_code')->nullable()->index();
            $table->unsignedBigInteger('cost')->default(0);
            $table->unsignedInteger('weight_grams')->nullable();
            $table->json('label_data')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('number')->unique();
            $table->string('type', 24)->default('sale');
            $table->boolean('is_official')->default(false);
            $table->json('snapshot');
            $table->timestamp('issued_at');
            $table->timestamps();
        });

        Schema::create('returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number')->unique();
            $table->string('status', 24)->default('requested')->index();
            $table->string('reason_code')->nullable();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('requested_refund')->default(0);
            $table->unsignedBigInteger('approved_refund')->default(0);
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });

        Schema::create('return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('condition', 24)->nullable();
            $table->boolean('restock')->default(false);
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('return_id')->nullable()->constrained('returns')->nullOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('status', 24)->default('pending');
            $table->string('reference_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('sms_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('body');
            $table->string('provider_pattern')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sms_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 32)->index();
            $table->string('mobile', 20)->index();
            $table->string('template_key')->nullable()->index();
            $table->text('body');
            $table->string('status', 24)->default('queued')->index();
            $table->string('provider_message_id')->nullable()->index();
            $table->unsignedInteger('cost')->nullable();
            $table->json('meta')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event', 64);
            $table->boolean('sms')->default(true);
            $table->boolean('email')->default(false);
            $table->boolean('database')->default(true);
            $table->boolean('marketing')->default(false);
            $table->timestamps();
            $table->unique(['user_id','event']);
        });
    }

    /** Drops payment and fulfilment infrastructure in reverse dependency order. */
    public function down(): void
    {
        foreach (['notification_preferences','sms_messages','sms_templates','refunds','return_items','returns','invoices','shipments','shipping_methods','wallet_entries','wallets','payment_transactions'] as $table) Schema::dropIfExists($table);
    }
};
