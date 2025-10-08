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
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('instructor_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('imported_at');
            $table->unsignedBigInteger('imported_by');
            $table->string('source')->default('Google Forms');
            $table->integer('total_responses')->default(0);
            $table->json('metadata')->nullable(); // Dodatkowe metadane
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('instructor_id')->references('id')->on('instructors')->onDelete('set null');
            $table->foreign('imported_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surveys');
    }
};
