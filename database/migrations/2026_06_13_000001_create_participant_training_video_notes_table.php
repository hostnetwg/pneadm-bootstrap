<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participant_training_video_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('participant_id');
            $table->foreign('participant_id', 'fk_ptvn_participant')
                ->references('id')->on('participants')->cascadeOnDelete();
            $table->unsignedBigInteger('course_video_id');
            $table->foreign('course_video_id', 'fk_ptvn_course_video')
                ->references('id')->on('course_videos')->cascadeOnDelete();
            $table->longText('body');
            $table->timestamps();

            $table->unique(['participant_id', 'course_video_id'], 'uq_ptvn_participant_video');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_training_video_notes');
    }
};
