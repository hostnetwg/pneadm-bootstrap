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
        Schema::create('survey_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('survey_id');
            $table->string('question_text');
            $table->enum('question_type', ['rating', 'text', 'multiple_choice', 'single_choice', 'date', 'time']);
            $table->integer('question_order');
            $table->json('options')->nullable(); // Opcje dla pytań wielokrotnego wyboru
            $table->timestamps();

            $table->foreign('survey_id')->references('id')->on('surveys')->onDelete('cascade');
            $table->index(['survey_id', 'question_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('survey_questions');
    }
};
