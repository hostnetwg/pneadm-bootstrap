<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_online_details', function (Blueprint $table) {
            $table->string('clickmeeting_event_id')->nullable()->after('meeting_password');
            $table->index('clickmeeting_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('course_online_details', function (Blueprint $table) {
            $table->dropIndex(['clickmeeting_event_id']);
            $table->dropColumn('clickmeeting_event_id');
        });
    }
};
