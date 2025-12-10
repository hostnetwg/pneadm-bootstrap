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
        if (Schema::hasTable('course_videos')) {
            return;
        }
        
        Schema::create('course_videos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->string('video_url');
            $table->enum('platform', ['youtube', 'vimeo'])->default('youtube');
            $table->string('title')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->foreign('course_id')
                  ->references('id')
                  ->on('courses')
                  ->onDelete('cascade');

            $table->index(['course_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_videos');
    }
};

