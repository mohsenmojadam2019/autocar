<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Builds CRM, campaigns, content, mega-menu, reviews, support, wholesale and SEO redirects. */
    public function up(): void
    {
        Schema::create('customer_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('company_name')->nullable();
            $table->string('national_id')->nullable();
            $table->string('economic_code')->nullable();
            $table->string('customer_group', 32)->default('retail')->index();
            $table->unsignedBigInteger('lifetime_value')->default(0);
            $table->unsignedInteger('orders_count')->default(0);
            $table->timestamps();
        });
        Schema::create('customer_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });
        Schema::create('customer_tag_user', function (Blueprint $table): void {
            $table->foreignId('customer_tag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['customer_tag_id', 'user_id']);
        });
        Schema::create('customer_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note');
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
        });
        Schema::create('marketing_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 24);
            $table->boolean('granted')->default(false);
            $table->string('source')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('changed_at')->useCurrent();
            $table->unique(['user_id', 'channel']);
        });
        Schema::create('customer_segments', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->json('rules');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('sms_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('segment_id')->nullable()->constrained('customer_segments')->nullOnDelete();
            $table->string('status', 24)->default('draft')->index();
            $table->text('message');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('rate_per_minute')->default(60);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamps();
        });
        Schema::create('sms_campaign_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sms_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mobile', 20);
            $table->string('status', 24)->default('pending')->index();
            $table->foreignId('sms_message_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['sms_campaign_id', 'mobile']);
        });
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('body')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->string('template')->default('default');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->string('cover_path')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('faqs', function (Blueprint $table): void {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->string('group')->nullable()->index();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('banners', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('placement')->index();
            $table->string('image_path');
            $table->string('mobile_image_path')->nullable();
            $table->string('url')->nullable();
            $table->string('alt')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('menu', 32)->default('main')->index();
            $table->string('title');
            $table->string('type', 24)->default('link');
            $table->string('url')->nullable();
            $table->string('icon')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('columns')->default(1);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('mobile_visible')->default(true);
            $table->boolean('desktop_visible')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->boolean('verified_purchase')->default(false);
            $table->text('admin_reply')->nullable();
            $table->timestamps();
        });
        Schema::create('product_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('question');
            $table->text('answer')->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->timestamps();
        });
        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('department', 32)->default('support')->index();
            $table->string('priority', 16)->default('normal');
            $table->string('status', 24)->default('open')->index();
            $table->string('subject');
            $table->timestamp('first_response_due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
        Schema::create('ticket_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->json('attachments')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('wholesale_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('pending');
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->unsignedBigInteger('credit_limit')->default(0);
            $table->string('company_name')->nullable();
            $table->string('tax_id')->nullable();
            $table->timestamps();
        });
        Schema::create('wholesale_quotes', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('user_id')->constrained();
            $table->string('status', 24)->default('requested');
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
        Schema::create('wholesale_quote_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wholesale_quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_variant_id')->nullable()->constrained();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('line_total');
            $table->timestamps();
        });
        Schema::create('seo_redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('from_path')->unique();
            $table->string('to_url');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamps();
        });
    }

    /** Drops CRM/content/support tables in reverse dependency order. */
    public function down(): void
    {
        foreach (['seo_redirects', 'wholesale_quote_items', 'wholesale_quotes', 'wholesale_accounts', 'ticket_messages', 'tickets', 'product_questions', 'reviews', 'menu_items', 'banners', 'faqs', 'posts', 'pages', 'sms_campaign_recipients', 'sms_campaigns', 'customer_segments', 'marketing_consents', 'customer_notes', 'customer_tag_user', 'customer_tags', 'customer_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
