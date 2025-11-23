<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabela pivot dla relacji Many-to-Many między courses a course_series
     * Pozwala na przypisanie kursu do wielu serii z niezależną kolejnością w każdej serii
     */
    public function up(): void
    {
        Schema::create('course_series_course', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_series_id')
                  ->constrained('course_series')
                  ->onDelete('cascade');
            $table->foreignId('course_id')
                  ->constrained('courses')
                  ->onDelete('cascade');
            $table->integer('order_in_series')
                  ->default(0)
                  ->comment('Kolejność kursu w danej serii');
            $table->timestamps();

            // Unikalna kombinacja - kurs może być w serii tylko raz
            $table->unique(['course_series_id', 'course_id']);
            
            // Indeksy dla wydajności
            $table->index('course_series_id');
            $table->index('course_id');
            $table->index(['course_series_id', 'order_in_series']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_series_course');
    }
};

