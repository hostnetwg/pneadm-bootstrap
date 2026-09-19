<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_online_details', function (Blueprint $table) {
            $table->unsignedBigInteger('live_offer_course_id')
                ->nullable()
                ->after('live_bar_survey_enabled');
            $table->boolean('live_offer_enabled')
                ->default(false)
                ->after('live_offer_course_id');
            $table->foreign('live_offer_course_id')
                ->references('id')
                ->on('courses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('course_online_details', function (Blueprint $table) {
            $table->dropForeign(['live_offer_course_id']);
            $table->dropColumn(['live_offer_course_id', 'live_offer_enabled']);
        });
    }
};
