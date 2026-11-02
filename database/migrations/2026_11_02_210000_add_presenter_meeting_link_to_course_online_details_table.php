<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_online_details', function (Blueprint $table) {
            $table->string('presenter_meeting_link', 512)->nullable()->after('meeting_link');
        });
    }

    public function down(): void
    {
        Schema::table('course_online_details', function (Blueprint $table) {
            $table->dropColumn('presenter_meeting_link');
        });
    }
};
