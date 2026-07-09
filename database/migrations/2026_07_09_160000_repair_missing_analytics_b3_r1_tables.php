<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Naprawa lokalnych/prod środowisk, gdzie migracje B3/R1 są oznaczone jako wykonane,
 * ale tabele agregatów zostały usunięte lub nigdy nie utworzono na pne_analytics.
 */
return new class extends Migration
{
    private function analyticsConnection(): string
    {
        return (string) config('database.analytics_connection', 'analytics');
    }

    public function up(): void
    {
        $connection = $this->analyticsConnection();

        if (! Schema::connection($connection)->hasTable('analytics_daily_form_abandonment_stats')) {
            Schema::connection($connection)->create('analytics_daily_form_abandonment_stats', function (Blueprint $table) {
                $table->id();
                $table->date('stat_date')->index();
                $table->unsignedBigInteger('course_id')->index();
                $table->string('course_title_snapshot', 255)->nullable();
                $table->unsignedInteger('sessions_total')->default(0);
                $table->unsignedInteger('reached_viewed')->default(0);
                $table->unsignedInteger('reached_started')->default(0);
                $table->unsignedInteger('reached_submit_clicked')->default(0);
                $table->unsignedInteger('reached_submit_attempted')->default(0);
                $table->unsignedInteger('reached_created')->default(0);
                $table->unsignedInteger('viewed_not_started')->default(0);
                $table->unsignedInteger('started_not_submit_clicked')->default(0);
                $table->unsignedInteger('submit_clicked_not_attempted')->default(0);
                $table->unsignedInteger('submit_attempted_not_created')->default(0);
                $table->unsignedInteger('converted')->default(0);
                $table->timestamps();
                $table->unique(['stat_date', 'course_id'], 'form_abandonment_uq');
                $table->index(['course_id', 'stat_date'], 'form_abandonment_course_idx');
            });
        }

        if (! Schema::connection($connection)->hasTable('analytics_daily_campaign_abandonment_stats')) {
            Schema::connection($connection)->create('analytics_daily_campaign_abandonment_stats', function (Blueprint $table) {
                $table->id();
                $table->date('stat_date')->index();
                $table->string('campaign_code', 100)->index();
                $table->unsignedBigInteger('campaign_id')->nullable()->index();
                $table->unsignedInteger('sessions_total')->default(0);
                $table->unsignedInteger('reached_viewed')->default(0);
                $table->unsignedInteger('reached_started')->default(0);
                $table->unsignedInteger('reached_submit_clicked')->default(0);
                $table->unsignedInteger('reached_submit_attempted')->default(0);
                $table->unsignedInteger('reached_created')->default(0);
                $table->unsignedInteger('viewed_not_started')->default(0);
                $table->unsignedInteger('started_not_submit_clicked')->default(0);
                $table->unsignedInteger('submit_clicked_not_attempted')->default(0);
                $table->unsignedInteger('submit_attempted_not_created')->default(0);
                $table->unsignedInteger('converted')->default(0);
                $table->timestamps();
                $table->unique(['stat_date', 'campaign_code'], 'campaign_abandonment_uq');
                $table->index(['campaign_code', 'stat_date'], 'campaign_abandonment_code_idx');
                $table->index(['campaign_id', 'stat_date'], 'campaign_abandonment_id_idx');
            });
        }

        if (! Schema::connection($connection)->hasTable('analytics_daily_course_revenue_stats')) {
            Schema::connection($connection)->create('analytics_daily_course_revenue_stats', function (Blueprint $table) {
                $table->id();
                $table->date('stat_date')->index();
                $table->unsignedBigInteger('course_id');
                $table->string('course_title_snapshot', 255)->nullable();
                $table->unsignedInteger('orders_created')->default(0);
                $table->decimal('ordered_revenue_gross', 12, 2)->default(0);
                $table->unsignedInteger('online_paid_orders')->default(0);
                $table->decimal('online_paid_revenue_gross', 12, 2)->default(0);
                $table->unsignedInteger('deferred_invoiced_orders')->default(0);
                $table->decimal('deferred_invoiced_revenue_gross', 12, 2)->default(0);
                $table->unsignedInteger('online_invoiced_marker_orders')->default(0);
                $table->unsignedInteger('settled_orders_total')->default(0);
                $table->decimal('settled_revenue_gross', 12, 2)->default(0);
                $table->unsignedInteger('orders_created_without_campaign')->default(0);
                $table->unsignedInteger('online_paid_without_campaign')->default(0);
                $table->unsignedInteger('deferred_invoiced_without_campaign')->default(0);
                $table->timestamps();
                $table->unique(['stat_date', 'course_id'], 'course_revenue_uq');
                $table->index(['course_id', 'stat_date'], 'course_revenue_course_idx');
            });
        }

        if (! Schema::connection($connection)->hasTable('analytics_daily_campaign_revenue_stats')) {
            Schema::connection($connection)->create('analytics_daily_campaign_revenue_stats', function (Blueprint $table) {
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
                $table->unique(['stat_date', 'campaign_code'], 'campaign_revenue_uq');
                $table->index(['campaign_code', 'stat_date'], 'campaign_revenue_code_idx');
                $table->index(['campaign_id', 'stat_date'], 'campaign_revenue_id_idx');
            });
        }
    }

    public function down(): void
    {
        // Świadomie puste — migracja naprawcza; nie usuwamy tabel przy rollbacku.
    }
};
