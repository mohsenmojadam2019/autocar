<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Stores card-to-card/manual-payment evidence awaiting explicit admin approval. */
    public function up(): void
    {
        Schema::create('manual_payment_proofs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_code')->nullable()->index();
            $table->string('card_last4', 4)->nullable();
            $table->unsignedBigInteger('amount');
            $table->timestamp('paid_at')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('status', 24)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    /** Removes manual payment proofs. */
    public function down(): void
    {
        Schema::dropIfExists('manual_payment_proofs');
    }
};
