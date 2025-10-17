<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Zwiększa rozmiary kolumn, które powodowały błędy podczas migracji danych
     * (72 rekordy nie zostały zmigrowane z powodu przekroczenia długości)
     */
    public function up(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            // IP address - niektóre rekordy mają powtórzone wartości
            // np. "100.107.44.135, 100.107.44.135, 100.107.44.135"
            $table->string('ip_address', 150)->nullable()->change();
            
            // Kody pocztowe - niektóre mają nietypowe formaty
            $table->string('orderer_postal_code', 50)->nullable()->change();
            $table->string('buyer_postal_code', 50)->nullable()->change();
            $table->string('recipient_postal_code', 50)->nullable()->change();
            
            // NIP - niektóre mają dodatkowe znaki lub nietypowe formaty
            $table->string('buyer_nip', 50)->nullable()->change();
            $table->string('recipient_nip', 50)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            // Przywrócenie oryginalnych rozmiarów
            $table->string('ip_address', 45)->nullable()->change();
            $table->string('orderer_postal_code', 10)->nullable()->change();
            $table->string('buyer_postal_code', 10)->nullable()->change();
            $table->string('recipient_postal_code', 10)->nullable()->change();
            $table->string('buyer_nip', 20)->nullable()->change();
            $table->string('recipient_nip', 20)->nullable()->change();
        });
    }
};
