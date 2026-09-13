<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            $table->string('order_kind', 32)
                ->default('training')
                ->after('order_date')
                ->index()
                ->comment('training = legacy courses; product = katalog products/order_items');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_order_id')->constrained('form_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_offer_id')->nullable()->constrained('product_offers')->nullOnDelete();
            $table->foreignId('product_price_id')->nullable()->constrained('product_prices')->nullOnDelete();
            $table->string('product_type', 32);
            $table->string('product_name');
            $table->string('product_sku', 100)->nullable();
            $table->string('fulfillment_type', 50);
            $table->boolean('requires_shipping')->default(false);
            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 10, 2);
            $table->string('currency', 3)->default('PLN');
            $table->string('tax_treatment', 20);
            $table->decimal('tax_rate', 5, 4)->nullable();
            $table->string('tax_exemption_basis', 500)->nullable();
            $table->string('access_policy', 32)->nullable();
            $table->unsignedSmallInteger('access_duration_value')->nullable();
            $table->string('access_duration_unit', 16)->nullable();
            $table->timestamp('access_expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at'], 'idx_order_items_product_created');
        });

        Schema::create('order_item_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('form_order_participant_id')->nullable()->constrained('form_order_participants')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('status', 32)->default('pending');
            $table->timestamps();

            $table->unique(['order_item_id', 'email'], 'uq_order_item_recipients_item_email');
            $table->index(['status', 'created_at'], 'idx_order_item_recipients_status');
        });

        Schema::create('order_fulfillments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_recipient_id')->constrained('order_item_recipients')->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('idempotency_key', 191)->unique();
            $table->string('status', 32)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedBigInteger('pnedu_user_id')->nullable()->comment('ID w odrębnej bazie pnedu; bez FK');
            $table->unsignedBigInteger('online_course_enrollment_id')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('previous_access_expires_at')->nullable();
            $table->timestamp('new_access_expires_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['order_item_recipient_id', 'type'], 'uq_order_fulfillments_recipient_type');
            $table->index(['status', 'updated_at'], 'idx_order_fulfillments_status');
            $table->foreign('online_course_enrollment_id', 'fk_order_fulfillments_online_enrollment')
                ->references('id')
                ->on('online_course_enrollments')
                ->nullOnDelete();
        });

        Schema::table('online_payment_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('course_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('online_payment_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('course_id')->nullable(false)->change();
        });

        Schema::dropIfExists('order_fulfillments');
        Schema::dropIfExists('order_item_recipients');
        Schema::dropIfExists('order_items');

        Schema::table('form_orders', function (Blueprint $table) {
            $table->dropIndex(['order_kind']);
            $table->dropColumn('order_kind');
        });
    }
};
