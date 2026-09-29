<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('growth_tasks', function (Blueprint $table) {
            $table->string('key', 80)->nullable()->after('growth_campaign_id');
            $table->unique(['growth_campaign_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::table('growth_tasks', function (Blueprint $table) {
            $table->dropUnique(['growth_campaign_id', 'key']);
            $table->dropColumn('key');
        });
    }
};
