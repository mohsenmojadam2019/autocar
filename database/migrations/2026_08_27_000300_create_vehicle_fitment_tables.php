<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Builds normalized vehicle data, customer garages and product fitment rules. */
    public function up(): void
    {
        Schema::create('vehicle_makes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('vehicle_models', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_make_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('slug');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['vehicle_make_id', 'slug']);
        });

        Schema::create('vehicle_generations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_model_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('from_year')->nullable();
            $table->unsignedSmallInteger('to_year')->nullable();
            $table->string('body_type')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicle_engines', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->nullable()->index();
            $table->string('name');
            $table->unsignedSmallInteger('displacement_cc')->nullable();
            $table->string('fuel_type', 24)->nullable();
            $table->unsignedSmallInteger('power_hp')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicle_trims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_generation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_engine_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('year');
            $table->string('transmission', 32)->nullable();
            $table->string('drivetrain', 16)->nullable();
            $table->string('market', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['vehicle_generation_id', 'year']);
        });

        Schema::create('product_fitments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_make_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_model_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_generation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_trim_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_engine_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('from_year')->nullable();
            $table->unsignedSmallInteger('to_year')->nullable();
            $table->string('status', 24)->default('compatible');
            $table->boolean('is_exclusion')->default(false);
            $table->unsignedTinyInteger('confidence')->default(100);
            $table->string('source')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'status']);
        });

        Schema::create('customer_vehicles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_trim_id')->constrained()->cascadeOnDelete();
            $table->string('nickname')->nullable();
            $table->string('plate')->nullable();
            $table->string('vin')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'is_default']);
        });
    }

    /** Removes vehicle and fitment tables in dependency-safe order. */
    public function down(): void
    {
        Schema::dropIfExists('customer_vehicles');
        Schema::dropIfExists('product_fitments');
        Schema::dropIfExists('vehicle_trims');
        Schema::dropIfExists('vehicle_engines');
        Schema::dropIfExists('vehicle_generations');
        Schema::dropIfExists('vehicle_models');
        Schema::dropIfExists('vehicle_makes');
    }
};
