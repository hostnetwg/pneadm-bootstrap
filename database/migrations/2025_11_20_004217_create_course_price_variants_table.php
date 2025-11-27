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
        Schema::create('course_price_variants', function (Blueprint $table) {
            // Podstawowe pola Laravel
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
            
            // Pola dla wariantu ceny
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('price', 10, 2);
            
            // Pola dla promocji
            $table->boolean('is_promotion')->default(false);
            $table->decimal('promotion_price', 10, 2)->nullable();
            $table->enum('promotion_type', ['disabled', 'unlimited', 'time_limited'])->default('disabled');
            $table->dateTime('promotion_start')->nullable();
            $table->dateTime('promotion_end')->nullable();
            
            // Pola dla typu dostępu
            $table->enum('access_type', ['1', '2', '3', '4', '5'])->default('1');
            $table->dateTime('access_start_datetime')->nullable();
            $table->dateTime('access_end_datetime')->nullable();
            $table->integer('access_duration_value')->nullable();
            $table->enum('access_duration_unit', ['hours', 'days', 'months', 'years'])->nullable();
            
            // Indeksy dla optymalizacji
            $table->index('course_id');
            $table->index('is_active');
            $table->index(['promotion_type', 'promotion_start', 'promotion_end'], 'idx_promotion_dates');
            $table->index('access_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_price_variants');
    }
};
