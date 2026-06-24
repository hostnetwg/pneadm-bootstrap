<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $analyticsConnection = 'analytics';

    public function up(): void
    {
        Schema::connection($this->analyticsConnection)->table('analytics_events', function (Blueprint $table) {
            if (! Schema::connection($this->analyticsConnection)->hasColumn('analytics_events', 'device_type')) {
                $table->string('device_type', 32)->nullable()->after('referrer_domain');
            }

            if (! Schema::connection($this->analyticsConnection)->hasColumn('analytics_events', 'browser_family')) {
                $table->string('browser_family', 64)->nullable()->after('device_type');
            }
        });
    }

    public function down(): void
    {
        Schema::connection($this->analyticsConnection)->table('analytics_events', function (Blueprint $table) {
            if (Schema::connection($this->analyticsConnection)->hasColumn('analytics_events', 'browser_family')) {
                $table->dropColumn('browser_family');
            }

            if (Schema::connection($this->analyticsConnection)->hasColumn('analytics_events', 'device_type')) {
                $table->dropColumn('device_type');
            }
        });
    }
};
