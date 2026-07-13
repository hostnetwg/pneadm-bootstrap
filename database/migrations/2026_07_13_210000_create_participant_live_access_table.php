<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participant_live_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('participants')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->unsignedBigInteger('form_order_id')->nullable();
            $table->string('platform', 32)->default('clickmeeting');
            $table->string('clickmeeting_event_id', 64)->nullable();
            $table->unsignedTinyInteger('access_type')->nullable();
            $table->string('room_url', 512)->nullable();
            $table->string('token', 64)->nullable();
            $table->string('status', 32)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique('participant_id');
            $table->foreign('form_order_id')->references('id')->on('form_orders')->nullOnDelete();
            $table->index('expires_at');
            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_live_access');
    }
};
