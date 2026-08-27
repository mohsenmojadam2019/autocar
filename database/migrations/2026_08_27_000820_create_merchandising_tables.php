<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Adds automatic timed promotions, scoped merchandising, bundles and banner analytics. */
    public function up(): void
    {
        Schema::create('automatic_promotions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('discount_type', 24)->default('percentage');
            $table->decimal('discount_value', 15, 2)->default(0);
            $table->unsignedBigInteger('max_discount')->nullable();
            $table->unsignedInteger('minimum_quantity')->default(1);
            $table->unsignedInteger('maximum_quantity')->nullable();
            $table->string('badge_text')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('stackable')->default(false);
            $table->json('conditions')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'starts_at', 'ends_at'], 'automatic_promotions_active_window');
        });

        Schema::create('automatic_promotion_product', function (Blueprint $table): void {
            $table->foreignId('automatic_promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['automatic_promotion_id', 'product_id'], 'auto_promo_product_primary');
        });

        Schema::create('automatic_promotion_category', function (Blueprint $table): void {
            $table->foreignId('automatic_promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['automatic_promotion_id', 'category_id'], 'auto_promo_category_primary');
        });

        Schema::create('automatic_promotion_brand', function (Blueprint $table): void {
            $table->foreignId('automatic_promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->primary(['automatic_promotion_id', 'brand_id'], 'auto_promo_brand_primary');
        });

        Schema::create('product_bundles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 24)->default('active')->index();
            $table->string('discount_type', 24)->default('percentage');
            $table->decimal('discount_value', 15, 2)->default(0);
            $table->string('badge_text')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('product_bundle_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_bundle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['product_bundle_id', 'product_id', 'product_variant_id'], 'bundle_item_unique');
        });

        Schema::create('banner_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('banner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_token', 128)->nullable()->index();
            $table->string('event', 16)->index();
            $table->string('placement')->nullable()->index();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['banner_id', 'event', 'created_at'], 'banner_event_reporting');
        });
    }

    /** Removes merchandising tables in reverse dependency order. */
    public function down(): void
    {
        foreach ([
            'banner_events',
            'product_bundle_items',
            'product_bundles',
            'automatic_promotion_brand',
            'automatic_promotion_category',
            'automatic_promotion_product',
            'automatic_promotions',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
