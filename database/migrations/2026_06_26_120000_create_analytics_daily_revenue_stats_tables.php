<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etap R1 — agregaty rozliczeń płatności/faktur.
 *
 * Dwie dzienne tabele agregatów (per kurs / per kampania) w bazie pne_analytics.
 * Metryki liczone WG DATY EVENTU (Europe/Warsaw):
 *  - zamówienia      → data form_order_created,
 *  - płatności online → data payment_status_changed (payment_status=paid, order_flow=online),
 *  - faktury odroczone → data invoice_created (order_flow=deferred).
 * Zero PII (patrz ADR-005).
 */
return new class extends Migration
{
    private string $analyticsConnection = 'analytics';

    public function up(): void
    {
        Schema::connection($this->analyticsConnection)->create('analytics_daily_course_revenue_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date')->index();
            $table->unsignedBigInteger('course_id');
            $table->string('course_title_snapshot', 255)->nullable();

            // Zamówione (wg daty form_order_created).
            $table->unsignedInteger('orders_created')->default(0);
            $table->decimal('ordered_revenue_gross', 12, 2)->default(0);

            // Opłacone online (wg daty payment_status_changed:paid, order_flow=online).
            $table->unsignedInteger('online_paid_orders')->default(0);
            $table->decimal('online_paid_revenue_gross', 12, 2)->default(0);

            // Zafakturowane odroczone (wg daty invoice_created, order_flow=deferred).
            $table->unsignedInteger('deferred_invoiced_orders')->default(0);
            $table->decimal('deferred_invoiced_revenue_gross', 12, 2)->default(0);

            // Marker księgowy: invoice_created z order_flow=online (NIE wchodzi do settled).
            $table->unsignedInteger('online_invoiced_marker_orders')->default(0);

            // Rozliczone łącznie (materializowane = online_paid + deferred_invoiced).
            $table->unsignedInteger('settled_orders_total')->default(0);
            $table->decimal('settled_revenue_gross', 12, 2)->default(0);

            // Diagnostyka (liczbowa, bez PII): event trafił do course stats, ale nie udało się
            // przypisać kampanii (brak campaign_code w evencie i brak FormOrder.fb_source).
            $table->unsignedInteger('orders_created_without_campaign')->default(0);
            $table->unsignedInteger('online_paid_without_campaign')->default(0);
            $table->unsignedInteger('deferred_invoiced_without_campaign')->default(0);

            $table->timestamps();

            $table->unique(['stat_date', 'course_id'], 'analytics_daily_course_revenue_unique');
            $table->index(['course_id', 'stat_date'], 'analytics_daily_course_revenue_course_date');
        });

        Schema::connection($this->analyticsConnection)->create('analytics_daily_campaign_revenue_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date')->index();
            $table->string('campaign_code', 100);
            $table->unsignedBigInteger('campaign_id')->nullable();

            $table->unsignedInteger('orders_created')->default(0);
            $table->decimal('ordered_revenue_gross', 12, 2)->default(0);

            $table->unsignedInteger('online_paid_orders')->default(0);
            $table->decimal('online_paid_revenue_gross', 12, 2)->default(0);

            $table->unsignedInteger('deferred_invoiced_orders')->default(0);
            $table->decimal('deferred_invoiced_revenue_gross', 12, 2)->default(0);

            $table->unsignedInteger('online_invoiced_marker_orders')->default(0);

            $table->unsignedInteger('settled_orders_total')->default(0);
            $table->decimal('settled_revenue_gross', 12, 2)->default(0);

            $table->timestamps();

            $table->unique(['stat_date', 'campaign_code'], 'analytics_daily_campaign_revenue_unique');
            $table->index(['campaign_code', 'stat_date'], 'analytics_daily_campaign_revenue_code_date');
            $table->index(['campaign_id', 'stat_date'], 'analytics_daily_campaign_revenue_id_date');
        });
    }

    public function down(): void
    {
        Schema::connection($this->analyticsConnection)->dropIfExists('analytics_daily_campaign_revenue_stats');
        Schema::connection($this->analyticsConnection)->dropIfExists('analytics_daily_course_revenue_stats');
    }
};
