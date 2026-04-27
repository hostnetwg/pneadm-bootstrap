<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('course_survey_links')) {
            return;
        }

        Schema::create('course_survey_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->string('url', 2048);
            $table->string('title')->nullable();
            // Auto-detekcja: google_forms | microsoft_forms | typeform | survey_monkey | other
            $table->string('provider', 50)->nullable();
            $table->boolean('is_active')->default(true);
            // Okno czasowe, w którym ankieta jest dostępna dla uczestników
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->foreign('course_id')
                ->references('id')
                ->on('courses')
                ->onDelete('cascade');

            $table->index(['course_id', 'order']);
            $table->index(['course_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_survey_links');
    }
};
