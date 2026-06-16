<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_campaigns', function (Blueprint $table) {
            $table->string('utm_content', 100)->nullable()->after('utm_medium');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_campaigns', function (Blueprint $table) {
            $table->dropColumn('utm_content');
        });
    }
};
