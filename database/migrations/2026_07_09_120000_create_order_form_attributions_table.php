<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function analyticsConnection(): string
    {
        return (string) config('database.analytics_connection', 'analytics');
    }

    public function up(): void
    {
        Schema::connection($this->analyticsConnection())->create('order_form_attributions', function (Blueprint $table) {
            $table->id();
            $table->uuid('form_session_id')->unique();
            $table->unsignedBigInteger('course_id')->nullable()->index();
            $table->unsignedBigInteger('price_variant_id')->nullable()->index();

            $table->string('current_source', 100)->nullable();
            $table->string('current_medium', 50)->nullable();
            $table->string('current_campaign', 255)->nullable();
            $table->string('current_content', 100)->nullable();
            $table->string('current_term', 100)->nullable();
            $table->string('current_referrer', 255)->nullable();
            $table->string('current_referrer_domain', 255)->nullable();
            $table->string('current_url', 500)->nullable();
            $table->string('current_channel', 50)->nullable();
            $table->string('current_attribution_source', 50)->nullable();

            $table->string('first_touch_source', 100)->nullable();
            $table->string('first_touch_medium', 50)->nullable();
            $table->string('first_touch_campaign', 255)->nullable();
            $table->string('first_touch_content', 100)->nullable();
            $table->string('first_touch_term', 100)->nullable();
            $table->string('first_touch_referrer', 255)->nullable();
            $table->string('first_touch_referrer_domain', 255)->nullable();
            $table->string('first_touch_landing_url', 500)->nullable();
            $table->string('first_touch_channel', 50)->nullable()->index();
            $table->string('first_touch_attribution_source', 50)->nullable();

            $table->string('last_touch_source', 100)->nullable();
            $table->string('last_touch_medium', 50)->nullable();
            $table->string('last_touch_campaign', 255)->nullable();
            $table->string('last_touch_content', 100)->nullable();
            $table->string('last_touch_term', 100)->nullable();
            $table->string('last_touch_referrer', 255)->nullable();
            $table->string('last_touch_referrer_domain', 255)->nullable();
            $table->string('last_touch_landing_url', 500)->nullable();
            $table->string('last_touch_channel', 50)->nullable();
            $table->string('last_touch_attribution_source', 50)->nullable();

            $table->string('last_external_touch_source', 100)->nullable();
            $table->string('last_external_touch_medium', 50)->nullable();
            $table->string('last_external_touch_campaign', 255)->nullable();
            $table->string('last_external_touch_content', 100)->nullable();
            $table->string('last_external_touch_term', 100)->nullable();
            $table->string('last_external_touch_referrer', 255)->nullable();
            $table->string('last_external_touch_referrer_domain', 255)->nullable();
            $table->string('last_external_touch_landing_url', 500)->nullable();
            $table->string('last_external_touch_channel', 50)->nullable()->index();
            $table->string('last_external_touch_attribution_source', 50)->nullable();

            $table->boolean('internal_promo_touched')->default(false);
            $table->string('internal_touch_source', 100)->nullable();
            $table->string('internal_touch_medium', 50)->nullable();
            $table->string('internal_touch_context', 100)->nullable();
            $table->string('internal_touch_path', 500)->nullable();
            $table->timestamp('internal_touch_at')->nullable();
            $table->string('internal_promo_id', 100)->nullable();
            $table->string('internal_promo_name', 255)->nullable();
            $table->string('internal_promo_placement', 100)->nullable();
            $table->string('internal_promo_context', 100)->nullable();

            $table->string('traffic_channel', 50)->nullable()->index();
            $table->string('traffic_source', 100)->nullable()->index();
            $table->string('traffic_medium', 50)->nullable()->index();
            $table->string('traffic_campaign', 255)->nullable()->index();
            $table->string('attribution_source', 50)->nullable();
            $table->string('conversion_reporting_channel', 50)->nullable()->index();

            $table->boolean('fbclid_present')->default(false);
            $table->boolean('gclid_present')->default(false);
            $table->unsignedSmallInteger('tracking_schema_version')->default(2);

            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection($this->analyticsConnection())->dropIfExists('order_form_attributions');
    }
};
