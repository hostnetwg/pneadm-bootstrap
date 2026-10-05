<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('growth_campaigns', function (Blueprint $table) {
            $table->string('registration_url', 500)->nullable()->after('ends_at');
            $table->string('youtube_live_url', 500)->nullable()->after('registration_url');
        });
    }

    public function down(): void
    {
        Schema::table('growth_campaigns', function (Blueprint $table) {
            $table->dropColumn(['registration_url', 'youtube_live_url']);
        });
    }
};
