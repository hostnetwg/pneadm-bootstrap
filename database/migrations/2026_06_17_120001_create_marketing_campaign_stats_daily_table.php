<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_campaign_stats_daily', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_code', 64);
            $table->date('stat_date');
            $table->unsignedInteger('link_entries')->default(0);
            $table->timestamps();

            $table->unique(['campaign_code', 'stat_date']);
            $table->index('stat_date');
            $table->index('campaign_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_campaign_stats_daily');
    }
};
