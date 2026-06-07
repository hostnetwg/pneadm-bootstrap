<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('google_calendar_event_id', 255)->nullable()->after('sendy_suppression_list_id');
            $table->string('google_calendar_html_link', 512)->nullable()->after('google_calendar_event_id');
            $table->string('google_calendar_sync_status', 20)->default('pending')->after('google_calendar_html_link');
            $table->timestamp('google_calendar_synced_at')->nullable()->after('google_calendar_sync_status');
            $table->text('google_calendar_sync_error')->nullable()->after('google_calendar_synced_at');

            $table->index('google_calendar_event_id');
            $table->index('google_calendar_sync_status');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['google_calendar_event_id']);
            $table->dropIndex(['google_calendar_sync_status']);
            $table->dropColumn([
                'google_calendar_event_id',
                'google_calendar_html_link',
                'google_calendar_sync_status',
                'google_calendar_synced_at',
                'google_calendar_sync_error',
            ]);
        });
    }
};
