<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_course_lesson_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('online_course_enrollment_id');
            $table->foreign('online_course_enrollment_id', 'fk_oc_less_note_en')
                ->references('id')->on('online_course_enrollments')->cascadeOnDelete();
            $table->unsignedBigInteger('online_course_lesson_id');
            $table->foreign('online_course_lesson_id', 'fk_oc_less_note_ls')
                ->references('id')->on('online_course_lessons')->cascadeOnDelete();
            $table->longText('body');
            $table->timestamps();

            $table->unique(['online_course_enrollment_id', 'online_course_lesson_id'], 'uq_oc_less_note_el');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_course_lesson_notes');
    }
};
