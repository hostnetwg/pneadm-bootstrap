<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCourseOnlineDetailsTable extends Migration
{
    public function up()
    {
        Schema::create('course_online_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->string('platform')->nullable(); // Zoom, Teams, Webex itp.
            $table->string('meeting_link')->nullable();
            $table->string('meeting_password')->nullable();
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('course_online_details');
    }
}
