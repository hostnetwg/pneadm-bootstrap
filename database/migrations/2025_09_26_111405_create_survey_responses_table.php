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
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('survey_id');
            $table->json('response_data'); // Wszystkie odpowiedzi w formacie JSON
            $table->timestamp('submitted_at');
            $table->string('respondent_id')->nullable(); // Opcjonalny identyfikator respondenta
            $table->timestamps();

            $table->foreign('survey_id')->references('id')->on('surveys')->onDelete('cascade');
            $table->index(['survey_id', 'submitted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
    }
};
