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
        $table = 'analytics_daily_course_channel_funnels';

        if (! Schema::connection($connection)->hasTable($table)) {
            return;
        }

        if (! Schema::connection($connection)->hasColumn($table, 'tracking_schema_version')) {
            Schema::connection($connection)->table($table, function (Blueprint $table) {
                $table->unsignedSmallInteger('tracking_schema_version')
                    ->default(2)
                    ->after('conversion_reporting_channel');
            });
        }
    }

    public function down(): void
    {
        $connection = $this->analyticsConnection();
        $table = 'analytics_daily_course_channel_funnels';

        if (Schema::connection($connection)->hasColumn($table, 'tracking_schema_version')) {
            Schema::connection($connection)->table($table, function (Blueprint $table) {
                $table->dropColumn('tracking_schema_version');
            });
        }
    }
};
