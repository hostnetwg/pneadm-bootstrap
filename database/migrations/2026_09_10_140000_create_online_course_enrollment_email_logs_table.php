<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_course_enrollment_email_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('online_course_id');
            $table->unsignedBigInteger('online_course_enrollment_id');
            $table->string('type', 32);
            $table->string('status', 16);
            $table->uuid('batch_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['online_course_id', 'type', 'status'], 'oceel_course_type_status_idx');
            $table->index(['online_course_enrollment_id', 'type', 'status'], 'oceel_enrollment_type_status_idx');
            $table->index(['batch_id'], 'oceel_batch_idx');

            $table->foreign('online_course_id', 'oceel_course_fk')
                ->references('id')->on('online_courses')->cascadeOnDelete();
            $table->foreign('online_course_enrollment_id', 'oceel_enrollment_fk')
                ->references('id')->on('online_course_enrollments')->cascadeOnDelete();
            $table->foreign('created_by', 'oceel_created_by_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_course_enrollment_email_logs');
    }
};
