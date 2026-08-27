<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Adds natural/legal customer identity and immutable invoice-recipient profiles. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('account_type', 16)->default('natural')->index()->after('mobile');
            $table->string('national_code', 10)->nullable()->unique()->after('account_type');
            $table->string('legal_name')->nullable()->after('national_code');
            $table->string('national_id', 11)->nullable()->unique()->after('legal_name');
            $table->string('economic_code', 20)->nullable()->after('national_id');
            $table->string('registration_number', 40)->nullable()->after('economic_code');
        });

        Schema::create('billing_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16)->default('natural')->index();
            $table->string('title')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('full_name')->nullable();
            $table->string('national_code', 10)->nullable();
            $table->string('company_name')->nullable();
            $table->string('national_id', 11)->nullable();
            $table->string('economic_code', 20)->nullable();
            $table->string('registration_number', 40)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'type', 'is_default'], 'billing_profile_user_type_default');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('billing_profile_id')->nullable()->after('cart_id')->constrained('billing_profiles')->nullOnDelete();
            $table->string('buyer_type', 16)->default('natural')->after('source');
            $table->string('invoice_kind', 16)->default('natural')->after('buyer_type');
            $table->json('billing_profile_snapshot')->nullable()->after('billing_address');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('invoice_kind', 16)->default('natural')->after('type');
            $table->json('buyer_snapshot')->nullable()->after('snapshot');
            $table->json('seller_snapshot')->nullable()->after('buyer_snapshot');
        });
    }

    /** Removes identity extensions in reverse foreign-key order. */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['invoice_kind', 'buyer_snapshot', 'seller_snapshot']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('billing_profile_id');
            $table->dropColumn(['buyer_type', 'invoice_kind', 'billing_profile_snapshot']);
        });

        Schema::dropIfExists('billing_profiles');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['account_type', 'national_code', 'legal_name', 'national_id', 'economic_code', 'registration_number']);
        });
    }
};
