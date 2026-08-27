<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Builds products, variants, media, specifications and catalog relations. */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('slug')->unique();
            $table->string('sku')->unique();
            $table->string('oem_code')->nullable()->index();
            $table->string('manufacturer_code')->nullable()->index();
            $table->string('authenticity', 24)->default('company');
            $table->string('status', 24)->default('draft')->index();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->string('warranty')->nullable();
            $table->unsignedSmallInteger('return_days')->default(7);
            $table->unsignedInteger('weight_grams')->nullable();
            $table->unsignedInteger('length_mm')->nullable();
            $table->unsignedInteger('width_mm')->nullable();
            $table->unsignedInteger('height_mm')->nullable();
            $table->unsignedInteger('minimum_order_quantity')->default(1);
            $table->unsignedInteger('maximum_order_quantity')->nullable();
            $table->unsignedBigInteger('purchase_price')->nullable();
            $table->unsignedBigInteger('sale_price');
            $table->unsignedBigInteger('compare_at_price')->nullable();
            $table->unsignedBigInteger('wholesale_price')->nullable();
            $table->boolean('is_taxable')->default(true);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->timestamp('published_at')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['brand_id', 'status']);
        });

        Schema::create('category_product', function (Blueprint $table): void {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->primary(['category_id', 'product_id']);
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->unsignedBigInteger('purchase_price')->nullable();
            $table->unsignedBigInteger('sale_price');
            $table->unsignedBigInteger('compare_at_price')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('product_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 32)->default('public');
            $table->string('path');
            $table->string('type', 16)->default('image');
            $table->string('alt')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('product_attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_option_id')->nullable()->constrained()->nullOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'attribute_id']);
        });

        Schema::create('product_relations', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_product_id')->constrained('products')->cascadeOnDelete();
            $table->string('type', 24);
            $table->unsignedInteger('position')->default(0);
            $table->primary(['product_id', 'related_product_id', 'type']);
        });
    }

    /** Removes product tables in dependency-safe order. */
    public function down(): void
    {
        Schema::dropIfExists('product_relations');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_media');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('products');
    }
};
