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
        $connection = $this->analyticsConnection();

        foreach (['analytics_daily_channel_funnels', 'analytics_daily_course_channel_funnels'] as $tableName) {
            Schema::connection($connection)->table($tableName, function (Blueprint $table) {
                $table->unsignedInteger('pending_after_submit_clicked')->default(0)->after('abandoned_after_submit_clicked');
                $table->unsignedInteger('validation_abandonment')->default(0)->after('pending_after_submit_clicked');
                $table->unsignedInteger('server_validation_abandonment')->default(0)->after('validation_abandonment');
                $table->unsignedInteger('backend_result_missing')->default(0)->after('server_validation_abandonment');
            });
        }

        Schema::connection($connection)->table('analytics_daily_data_quality', function (Blueprint $table) {
            $table->unsignedInteger('sessions_with_schema_v2_events')->default(0)->after('sessions_without_campaign');
            $table->decimal('schema_v2_event_rate', 8, 4)->default(0)->after('sessions_with_schema_v2_events');
            $table->json('tracking_data_quality_flags')->nullable()->after('tracking_data_quality_status');
            $table->unsignedTinyInteger('tracking_data_quality_score')->default(0)->after('tracking_data_quality_flags');
        });
    }

    public function down(): void
    {
        $connection = $this->analyticsConnection();

        foreach (['analytics_daily_channel_funnels', 'analytics_daily_course_channel_funnels'] as $tableName) {
            Schema::connection($connection)->table($tableName, function (Blueprint $table) {
                $table->dropColumn([
                    'pending_after_submit_clicked',
                    'validation_abandonment',
                    'server_validation_abandonment',
                    'backend_result_missing',
                ]);
            });
        }

        Schema::connection($connection)->table('analytics_daily_data_quality', function (Blueprint $table) {
            $table->dropColumn([
                'sessions_with_schema_v2_events',
                'schema_v2_event_rate',
                'tracking_data_quality_flags',
                'tracking_data_quality_score',
            ]);
        });
    }
};
