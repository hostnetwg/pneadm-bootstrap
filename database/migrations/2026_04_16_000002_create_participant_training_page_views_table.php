<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('participant_training_page_views')) {
            return;
        }

        Schema::create('participant_training_page_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('participant_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedInteger('open_count')->default(0);
            $table->timestamp('first_opened_at')->nullable();
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamps();

            $table->unique('participant_id', 'ptpv_participant_unique');
            $table->index(['course_id', 'last_opened_at'], 'ptpv_course_last_opened_idx');

            $table->foreign('participant_id')->references('id')->on('participants')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_training_page_views');
    }
};

