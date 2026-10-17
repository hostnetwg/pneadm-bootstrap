<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('closed_training_cases', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32);
            $table->unsignedBigInteger('training_offer_id')->nullable();
            $table->string('status', 32)->default('new');
            $table->unsignedBigInteger('assignee_user_id')->nullable();
            $table->string('topic');
            $table->string('offer_url', 2048)->nullable();
            $table->string('preferred_format', 32)->nullable();
            $table->string('contact_name');
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->string('institution')->nullable();
            $table->text('message');
            $table->timestamp('consent_at')->nullable();
            $table->text('next_action')->nullable();
            $table->dateTime('next_action_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->foreign('training_offer_id', 'ct_cases_offer_fk')->references('id')->on('training_offers')->nullOnDelete();
            $table->foreign('assignee_user_id', 'ct_cases_assignee_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'next_action_at'], 'ct_cases_status_next_idx');
            $table->index('contact_email', 'ct_cases_email_idx');
        });

        Schema::create('closed_training_case_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('closed_training_case_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event_type', 32);
            $table->text('body')->nullable();
            $table->timestamp('happened_at');
            $table->timestamps();

            $table->foreign('closed_training_case_id', 'ct_events_case_fk')->references('id')->on('closed_training_cases')->cascadeOnDelete();
            $table->foreign('user_id', 'ct_events_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(['closed_training_case_id', 'happened_at'], 'ct_events_case_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('closed_training_case_events');
        Schema::dropIfExists('closed_training_cases');
    }
};
