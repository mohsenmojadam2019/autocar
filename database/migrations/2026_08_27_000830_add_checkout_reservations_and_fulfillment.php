<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Adds authoritative shipping/wallet snapshots and expiring per-order inventory reservations. */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('shipping_method_id')->nullable()->after('cart_id')->constrained('shipping_methods')->nullOnDelete();
            $table->unsignedBigInteger('wallet_total')->default(0)->after('discount_total');
        });

        Schema::create('inventory_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status', 24)->default('reserved')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->unique(['order_item_id', 'stock_item_id'], 'reservation_order_stock_unique');
            $table->index(['order_id', 'status'], 'reservation_order_status');
        });
    }

    /** Removes reservation records before dropping order checkout linkage columns. */
    public function down(): void
    {
        Schema::dropIfExists('inventory_reservations');
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shipping_method_id');
            $table->dropColumn('wallet_total');
        });
    }
};
