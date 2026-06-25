<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $analyticsConnection = 'analytics';

    public function up(): void
    {
        Schema::connection($this->analyticsConnection)->create('analytics_daily_form_abandonment_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date')->index();
            $table->unsignedBigInteger('course_id')->index();
            $table->string('course_title_snapshot', 255)->nullable();

            // Łączna liczba sesji formularza przypisanych do dnia (po pierwszym evencie formularza).
            $table->unsignedInteger('sessions_total')->default(0);

            // Zasięg lejka — liczba sesji, które dotarły do danego etapu (obecność eventu).
            $table->unsignedInteger('reached_viewed')->default(0);
            $table->unsignedInteger('reached_started')->default(0);
            $table->unsignedInteger('reached_submit_clicked')->default(0);
            $table->unsignedInteger('reached_submit_attempted')->default(0);
            $table->unsignedInteger('reached_created')->default(0);

            // Kubełki terminalne (rozłączne): sumują się do sessions_total.
            $table->unsignedInteger('viewed_not_started')->default(0);
            $table->unsignedInteger('started_not_submit_clicked')->default(0);
            $table->unsignedInteger('submit_clicked_not_attempted')->default(0);
            $table->unsignedInteger('submit_attempted_not_created')->default(0);
            $table->unsignedInteger('converted')->default(0);

            $table->timestamps();

            $table->unique(['stat_date', 'course_id'], 'analytics_daily_form_abandonment_unique');
            // Pod przyszłe filtry B4: kurs w zakresie dat.
            $table->index(['course_id', 'stat_date'], 'analytics_daily_form_abandonment_course_date');
        });

        Schema::connection($this->analyticsConnection)->create('analytics_daily_campaign_abandonment_stats', function (Blueprint $table) {
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

            $table->unique(['stat_date', 'campaign_code'], 'analytics_daily_campaign_abandonment_unique');
            // Pod przyszłe filtry B4: kampania w zakresie dat.
            $table->index(['campaign_code', 'stat_date'], 'analytics_daily_campaign_abandonment_code_date');
            // campaign_id bywa null (kampania bez dopasowania w marketing_campaigns), ale B4 będzie
            // linkować/filtrować po campaign_id (jak dashboard sales-funnel), więc indeks się przyda.
            $table->index(['campaign_id', 'stat_date'], 'analytics_daily_campaign_abandonment_id_date');
        });
    }

    public function down(): void
    {
        Schema::connection($this->analyticsConnection)->dropIfExists('analytics_daily_campaign_abandonment_stats');
        Schema::connection($this->analyticsConnection)->dropIfExists('analytics_daily_form_abandonment_stats');
    }
};
