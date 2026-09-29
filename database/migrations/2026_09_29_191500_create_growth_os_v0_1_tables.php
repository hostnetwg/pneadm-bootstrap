<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 180);
            $table->string('type', 40);
            $table->string('status', 32)->default('draft');
            $table->string('goal', 120)->nullable();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('primary_instructor_id')->nullable()->constrained('instructors')->nullOnDelete();
            $table->string('working_topic', 180)->nullable();
            $table->text('summary')->nullable();
            $table->dateTime('live_at')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('type');
        });

        Schema::create('growth_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('growth_campaign_id')->constrained('growth_campaigns')->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('type', 40);
            $table->string('status', 32)->default('not_started');
            $table->string('title', 180)->nullable();
            $table->text('summary')->nullable();
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->unsignedInteger('version')->default(1);
            $table->json('payload')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['growth_campaign_id', 'key']);
            $table->index(['growth_campaign_id', 'status']);
        });

        Schema::create('growth_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('growth_campaign_id')->constrained('growth_campaigns')->cascadeOnDelete();
            $table->foreignId('growth_artifact_id')->nullable()->constrained('growth_artifacts')->nullOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('status', 32)->default('todo');
            $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['growth_campaign_id', 'status']);
            $table->index('due_at');
        });

        Schema::create('growth_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('growth_campaign_id')->constrained('growth_campaigns')->cascadeOnDelete();
            $table->foreignId('growth_artifact_id')->nullable()->constrained('growth_artifacts')->nullOnDelete();
            $table->foreignId('growth_task_id')->nullable()->constrained('growth_tasks')->nullOnDelete();
            $table->string('type', 40);
            $table->string('status', 32)->default('pending');
            $table->text('question')->nullable();
            $table->text('decision')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['growth_campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_decisions');
        Schema::dropIfExists('growth_tasks');
        Schema::dropIfExists('growth_artifacts');
        Schema::dropIfExists('growth_campaigns');
    }
};
