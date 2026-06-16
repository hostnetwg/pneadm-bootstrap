<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_page_stats_daily', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->date('stat_date');
            $table->unsignedInteger('views_course_show')->default(0);
            $table->unsignedInteger('views_order_form')->default(0);
            $table->timestamps();

            $table->unique(['course_id', 'stat_date']);
            $table->index('stat_date');
            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_page_stats_daily');
    }
};
