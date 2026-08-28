<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Stores an immutable audit trail for retail, purchase, compare-at and wholesale price changes. */
    public function up(): void
    {
        Schema::create('price_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('price_type', 32);
            $table->unsignedBigInteger('old_value')->nullable();
            $table->unsignedBigInteger('new_value')->nullable();
            $table->string('source', 32)->default('admin');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['product_id', 'price_type', 'created_at']);
            $table->index(['product_variant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_histories');
    }
};
