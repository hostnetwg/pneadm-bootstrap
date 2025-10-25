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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            
            // Użytkownik wykonujący akcję
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Typ akcji
            $table->enum('log_type', ['login', 'logout', 'create', 'update', 'delete', 'view', 'restore', 'custom'])
                  ->default('custom')
                  ->comment('Typ akcji: login, logout, create, update, delete, view, restore, custom');
            
            // Model którego dotyczy akcja (polymorphic)
            $table->string('model_type', 255)->nullable()->comment('Typ modelu np. Course, FormOrder');
            $table->unsignedBigInteger('model_id')->nullable()->comment('ID rekordu w modelu');
            $table->string('model_name', 500)->nullable()->comment('Czytelna nazwa rekordu (tytuł, nazwa)');
            
            // Szczegóły akcji
            $table->string('action', 500)->comment('Opis akcji np. "Zaktualizowano kurs", "Usunięto uczestnika"');
            $table->text('description')->nullable()->comment('Dodatkowy opis lub szczegóły');
            
            // Dane przed i po zmianie (dla update)
            $table->json('old_values')->nullable()->comment('Wartości przed zmianą (JSON)');
            $table->json('new_values')->nullable()->comment('Wartości po zmianie (JSON)');
            
            // Dane techniczne
            $table->string('ip_address', 45)->nullable()->comment('Adres IP użytkownika');
            $table->text('user_agent')->nullable()->comment('User Agent przeglądarki');
            $table->string('url', 500)->nullable()->comment('URL strony');
            $table->string('method', 10)->nullable()->comment('Metoda HTTP (GET, POST, PUT, DELETE)');
            
            // Timestamp
            $table->timestamp('created_at')->useCurrent()->comment('Data i czas akcji');
            
            // Indeksy dla optymalizacji wyszukiwania
            $table->index('user_id', 'idx_user_id');
            $table->index('log_type', 'idx_log_type');
            $table->index(['model_type', 'model_id'], 'idx_model');
            $table->index('created_at', 'idx_created_at');
            $table->index(['user_id', 'created_at'], 'idx_user_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
