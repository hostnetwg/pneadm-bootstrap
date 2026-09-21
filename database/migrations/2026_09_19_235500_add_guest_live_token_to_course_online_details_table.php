<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_online_details', function (Blueprint $table) {
            $table->string('guest_live_token', 64)
                ->nullable()
                ->unique()
                ->after('embed_email_link_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('course_online_details', function (Blueprint $table) {
            $table->dropUnique(['guest_live_token']);
            $table->dropColumn('guest_live_token');
        });
    }
};
