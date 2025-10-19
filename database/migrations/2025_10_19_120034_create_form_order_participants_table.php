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
        Schema::create('form_order_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_order_id');
            
            // Rozdzielone imię i nazwisko
            $table->string('participant_firstname', 100);
            $table->string('participant_lastname', 100);
            
            // Email uczestnika
            $table->string('participant_email', 255);
            
            // Czy to główny uczestnik (ten, który składał zamówienie)
            $table->boolean('is_primary')->default(0);
            
            $table->timestamps();
            
            // Klucz obcy do form_orders
            $table->foreign('form_order_id')
                  ->references('id')
                  ->on('form_orders')
                  ->onDelete('cascade');
            
            // Indeksy
            $table->index('form_order_id', 'idx_form_order');
            $table->index('participant_lastname', 'idx_lastname');
            $table->index('participant_email', 'idx_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_order_participants');
    }
};
