<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_course_migration_notices', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('status', 16);
            $table->uuid('batch_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['email', 'status'], 'ocmn_email_status_idx');
            $table->index('batch_id', 'ocmn_batch_idx');

            $table->foreign('created_by', 'ocmn_created_by_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_course_migration_notices');
    }
};
