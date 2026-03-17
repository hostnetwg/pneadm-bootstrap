<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_email_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('participant_id');
            $table->string('type', 32);   // list_link | single_certificate
            $table->string('status', 16); // queued | sent | failed
            $table->uuid('batch_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable(); // user_id z panelu
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'participant_id', 'type', 'status'], 'cel_course_part_type_status_idx');
            $table->index(['batch_id'], 'cel_batch_idx');

            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('participant_id')->references('id')->on('participants')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_email_logs');
    }
};

