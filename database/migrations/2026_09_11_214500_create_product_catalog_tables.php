<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32)->comment('Stabilny typ biznesowy, np. online_course, ebook, consultation, physical');
            $table->unsignedBigInteger('resource_id')->nullable()->comment('ID obiektu realizującego produkt; dla online_course = online_courses.id');
            $table->string('name');
            $table->string('slug', 191)->unique();
            $table->string('sku', 100)->nullable()->unique();
            $table->string('fulfillment_type', 50)->comment('Sposób realizacji, np. online_course_access');
            $table->boolean('is_active')->default(false);
            $table->boolean('requires_shipping')->default(false);
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['type', 'resource_id'], 'uq_products_type_resource');
            $table->index(['type', 'is_active'], 'idx_products_type_active');
        });

        Schema::create('product_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('code', 50)->default('default');
            $table->string('sales_channel', 50)->default('pnedu');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_public')->default(false);
            $table->boolean('allow_multiple_recipients')->default(true);
            $table->boolean('allow_deferred_invoice')->default(true);
            $table->boolean('allow_payu')->default(true);
            $table->boolean('allow_paynow')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['product_id', 'code', 'sales_channel'], 'uq_product_offers_product_code_channel');
            $table->index(['sales_channel', 'is_active', 'is_public'], 'idx_product_offers_public');
        });

        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_offer_id')->constrained('product_offers')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('PLN');
            $table->string('tax_treatment', 20)->default('exempt')->comment('exempt = ZW, standard = stawka procentowa');
            $table->decimal('tax_rate', 5, 4)->nullable()->comment('Ułamek, np. 0.2300 = 23%; null dla ZW');
            $table->string('tax_exemption_basis', 500)->nullable();
            $table->boolean('is_promotion')->default(false);
            $table->decimal('promotion_price', 10, 2)->nullable();
            $table->timestamp('promotion_starts_at')->nullable();
            $table->timestamp('promotion_ends_at')->nullable();
            $table->string('access_policy', 32)->default('unlimited')->comment('unlimited, duration_from_grant, fixed_until');
            $table->unsignedSmallInteger('access_duration_value')->nullable();
            $table->string('access_duration_unit', 16)->nullable()->comment('days, months, years');
            $table->timestamp('access_expires_at')->nullable()->comment('UTC; tylko dla fixed_until');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_offer_id', 'is_active', 'sort_order'], 'idx_product_prices_offer_active');
            $table->index(['is_promotion', 'promotion_starts_at', 'promotion_ends_at'], 'idx_product_prices_promotion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
        Schema::dropIfExists('product_offers');
        Schema::dropIfExists('products');
    }
};
