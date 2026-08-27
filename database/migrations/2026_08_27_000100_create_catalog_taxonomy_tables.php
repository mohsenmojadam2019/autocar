<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Builds unlimited catalog categories, parts brands and reusable attributes. */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('image_path')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('depth')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['parent_id', 'position']);
        });

        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('slug')->unique();
            $table->string('country_code', 2)->nullable();
            $table->string('logo_path')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('attribute_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('attributes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type', 24)->default('text');
            $table->string('unit')->nullable();
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_comparable')->default(false);
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('attribute_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->string('slug');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['attribute_id', 'slug']);
        });

        Schema::create('attribute_category', function (Blueprint $table): void {
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->primary(['attribute_id', 'category_id']);
        });
    }

    /** Removes catalog taxonomy tables in dependency-safe order. */
    public function down(): void
    {
        Schema::dropIfExists('attribute_category');
        Schema::dropIfExists('attribute_options');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('attribute_groups');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
