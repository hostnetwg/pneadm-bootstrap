<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_online_details', function (Blueprint $table) {
            $table->boolean('live_bar_attendance_enabled')
                ->default(false)
                ->after('embed_email_link_enabled');
            $table->boolean('live_bar_materials_enabled')
                ->default(false)
                ->after('live_bar_attendance_enabled');
            $table->boolean('live_bar_survey_enabled')
                ->default(false)
                ->after('live_bar_materials_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('course_online_details', function (Blueprint $table) {
            $table->dropColumn([
                'live_bar_attendance_enabled',
                'live_bar_materials_enabled',
                'live_bar_survey_enabled',
            ]);
        });
    }
};
