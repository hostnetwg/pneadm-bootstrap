<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCourseLocationsTable extends Migration
{
    public function up()
    {
        Schema::create('course_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->string('postal_code'); // Dodano kod pocztowy
            $table->string('post_office'); // Dodano pocztę
            $table->string('address');
            $table->string('country');
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('course_locations');
    }
}
