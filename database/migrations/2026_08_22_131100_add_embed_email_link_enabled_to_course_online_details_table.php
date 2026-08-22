<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_online_details', function (Blueprint $table) {
            $table->boolean('embed_email_link_enabled')
                ->default(true)
                ->after('embed_on_pnedu');
        });
    }

    public function down(): void
    {
        Schema::table('course_online_details', function (Blueprint $table) {
            $table->dropColumn('embed_email_link_enabled');
        });
    }
};
