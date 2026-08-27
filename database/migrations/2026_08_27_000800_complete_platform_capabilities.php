<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Completes cross-cutting platform capabilities required by the implementation plan. */
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 128);
            $table->string('name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'fingerprint']);
        });

        Schema::create('admin_ip_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('cidr', 64);
            $table->boolean('is_allowed')->default(true);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'is_allowed']);
        });

        Schema::create('feature_flags', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->boolean('enabled')->default(false);
            $table->unsignedTinyInteger('rollout_percentage')->default(100);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('search_synonyms', function (Blueprint $table): void {
            $table->id();
            $table->string('term', 190);
            $table->string('synonym', 190);
            $table->unsignedTinyInteger('weight')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['term', 'synonym']);
        });

        Schema::create('search_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_token', 128)->nullable()->index();
            $table->string('term', 190);
            $table->unsignedInteger('results_count')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('wishlists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('علاقه‌مندی‌ها');
            $table->boolean('is_default')->default(true);
            $table->string('share_token', 64)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('wishlist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wishlist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['wishlist_id', 'product_id']);
        });

        Schema::create('compare_lists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('session_token', 128)->nullable()->index();
            $table->string('share_token', 64)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('compare_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('compare_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['compare_list_id', 'product_id']);
        });

        Schema::create('supplier_product_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('supplier_sku')->nullable();
            $table->unsignedBigInteger('purchase_price');
            $table->unsignedInteger('minimum_quantity')->default(1);
            $table->unsignedSmallInteger('lead_time_days')->default(0);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['product_id', 'is_active']);
        });

        Schema::create('stock_transfers', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('completed');
            $table->text('note')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_transfer_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });

        Schema::create('stock_counts', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('completed');
            $table->text('note')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_count_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->integer('expected_quantity');
            $table->integer('counted_quantity');
            $table->integer('difference');
            $table->timestamps();
            $table->unique(['stock_count_id', 'stock_item_id']);
        });

        Schema::create('catalog_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_name');
            $table->string('mode', 24)->default('upsert');
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->string('report_path')->nullable();
            $table->timestamps();
        });

        Schema::create('catalog_import_errors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catalog_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->text('message');
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->json('rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('customer_group_user', function (Blueprint $table): void {
            $table->foreignId('customer_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['customer_group_id', 'user_id']);
        });

        Schema::create('gift_cards', function (Blueprint $table): void {
            $table->id();
            $table->string('code_hash', 64)->unique();
            $table->foreignId('issued_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('initial_balance');
            $table->unsignedBigInteger('balance');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('coupon_product', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'product_id']);
        });

        Schema::create('coupon_category', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'category_id']);
        });

        Schema::create('content_revisions', function (Blueprint $table): void {
            $table->id();
            $table->string('revisable_type');
            $table->unsignedBigInteger('revisable_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload');
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['revisable_type', 'revisable_id']);
        });

        Schema::create('review_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason');
            $table->string('status', 24)->default('pending');
            $table->text('moderator_note')->nullable();
            $table->timestamps();
            $table->unique(['review_id', 'user_id']);
        });

        Schema::create('part_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_trim_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mobile', 20);
            $table->string('part_name');
            $table->string('oem_code')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 24)->default('new');
            $table->text('admin_note')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_transaction_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24);
            $table->string('provider_reference')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();
            $table->index(['status', 'checked_at']);
        });

        Schema::create('shipping_zones', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->json('provinces')->nullable();
            $table->json('cities')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shipping_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipping_method_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('min_weight_grams')->default(0);
            $table->unsignedInteger('max_weight_grams')->nullable();
            $table->unsignedBigInteger('min_order_amount')->default(0);
            $table->unsignedBigInteger('max_order_amount')->nullable();
            $table->unsignedBigInteger('price');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shipment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('status', 50);
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        Schema::create('provider_health_checks', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_type', 32);
            $table->string('provider_name', 64);
            $table->string('status', 24);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('checked_at');
            $table->index(['provider_type', 'provider_name', 'checked_at'], 'provider_health_lookup');
        });

        Schema::create('backup_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 24);
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('checksum', 128)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /** Drops completion tables in reverse dependency order. */
    public function down(): void
    {
        foreach ([
            'backup_runs', 'provider_health_checks', 'shipment_events', 'shipping_rates', 'shipping_zones',
            'payment_reconciliations', 'notifications', 'part_requests', 'review_reports', 'content_revisions',
            'coupon_category', 'coupon_product', 'gift_cards', 'customer_group_user', 'customer_groups',
            'catalog_import_errors', 'catalog_imports', 'stock_count_items', 'stock_counts',
            'stock_transfer_items', 'stock_transfers', 'supplier_product_prices', 'compare_items', 'compare_lists',
            'wishlist_items', 'wishlists', 'search_histories', 'search_synonyms', 'feature_flags', 'admin_ip_rules', 'user_devices',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
