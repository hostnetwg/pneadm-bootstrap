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
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->enum('type', ['online', 'offline']);
            $table->enum('category', ['open', 'closed']);
            $table->unsignedBigInteger('instructor_id')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

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
