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
        Schema::create('participant_emails', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255)->unique();
            $table->unsignedBigInteger('first_participant_id')->nullable();
            $table->integer('participants_count')->default(0);
            $table->boolean('is_verified')->default(false)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraint
            $table->foreign('first_participant_id')
                  ->references('id')
                  ->on('participants')
                  ->onDelete('set null');

            // Indeksy dla lepszej wydajności
            $table->index('email');
            $table->index('first_participant_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('participant_emails');
    }
};

