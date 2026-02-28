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
        Schema::table('form_orders', function (Blueprint $table) {
            // Dodaj pola KSeF po invoice_number
            $table->string('ksef_number', 100)->nullable()->after('invoice_number')->comment('Numer KSeF faktury');
            $table->timestamp('ksef_sent_at')->nullable()->after('ksef_number')->comment('Data i czas przesłania do KSeF');
            $table->enum('ksef_status', ['pending', 'sent', 'failed'])->nullable()->after('ksef_sent_at')->comment('Status przesłania do KSeF');
            $table->text('ksef_error')->nullable()->after('ksef_status')->comment('Szczegóły błędu przesłania do KSeF');
            
            // Indeks dla szybkiego wyszukiwania
            $table->index('ksef_number', 'idx_ksef_number');
            $table->index('ksef_status', 'idx_ksef_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            $table->dropIndex('idx_ksef_status');
            $table->dropIndex('idx_ksef_number');
            $table->dropColumn(['ksef_error', 'ksef_status', 'ksef_sent_at', 'ksef_number']);
        });
    }
};
