<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCoursesTable extends Migration
{
    public function up()
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_old')->nullable()->index(); // dodajemy pole id_old  
            $table->string('source_id_old')->nullable()->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->boolean('is_paid')->default(true); // Domyślnie kursy są płatne            
            $table->enum('type', ['online', 'offline']);
            $table->enum('category', ['open', 'closed']);
            $table->unsignedBigInteger('instructor_id')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Dodanie unikalnego indeksu
            $table->unique(['id_old', 'source_id_old']);            

            $table->foreign('instructor_id')
                  ->references('id')
                  ->on('instructors')
                  ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('courses');
    }
}
