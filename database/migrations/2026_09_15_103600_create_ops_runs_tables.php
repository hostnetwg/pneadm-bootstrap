<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_runs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64)->index();
            $table->string('status', 32)->index();
            $table->string('title');
            $table->date('period_date')->nullable()->index();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ops_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ops_run_id')->constrained('ops_runs')->cascadeOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('status', 32)->index();
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['ops_run_id', 'subject_type', 'subject_id'], 'ops_run_items_subject_unique');
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_run_items');
        Schema::dropIfExists('ops_runs');
    }
};
