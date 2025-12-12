<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->string('website_url')->nullable()->after('notes');
            $table->string('linkedin_url')->nullable()->after('website_url');
            $table->string('facebook_url')->nullable()->after('linkedin_url');
            $table->string('youtube_url')->nullable()->after('facebook_url');
            $table->string('x_com_url')->nullable()->after('youtube_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->dropColumn(['website_url', 'linkedin_url', 'facebook_url', 'youtube_url', 'x_com_url']);
        });
    }
};
