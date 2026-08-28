<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_category', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['attribute_id', 'category_id']);
        });

        Schema::create('product_bundles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('discount_type', 24)->default('percentage');
            $table->decimal('discount_value', 14, 2)->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::create('product_bundle_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_bundle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['product_bundle_id', 'product_id']);
        });

        Schema::create('customer_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 16)->nullable();
            $table->timestamps();
        });

        Schema::create('customer_tag_user', function (Blueprint $table): void {
            $table->foreignId('customer_tag_id')->constrained('customer_tags')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['customer_tag_id', 'user_id']);
        });

        Schema::create('marketing_suppressions', function (Blueprint $table): void {
            $table->id();
            $table->string('channel', 24)->default('sms');
            $table->string('value', 190);
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['channel', 'value']);
        });

        Schema::create('cart_recoveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('status', 24)->default('pending')->index();
            $table->timestamp('eligible_at')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('recovered_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->unsignedBigInteger('wholesale_price')->nullable()->after('sale_price');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('wholesale_price'));
        Schema::dropIfExists('cart_recoveries');
        Schema::dropIfExists('marketing_suppressions');
        Schema::dropIfExists('customer_tag_user');
        Schema::dropIfExists('customer_tags');
        Schema::dropIfExists('product_bundle_items');
        Schema::dropIfExists('product_bundles');
        Schema::dropIfExists('attribute_category');
    }
};
