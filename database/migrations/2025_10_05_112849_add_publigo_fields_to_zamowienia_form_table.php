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
        // Dodanie pól Publigo do tabeli zamowienia_FORM w bazie certgen
        if (!Schema::connection('mysql_certgen')->hasTable('zamowienia_FORM')) {
            return; // Tabela nie istnieje, pomiń migrację
        }
        
        Schema::connection('mysql_certgen')->table('zamowienia_FORM', function (Blueprint $table) {
            if (!Schema::connection('mysql_certgen')->hasColumn('zamowienia_FORM', 'publigo_sent')) {
                $table->tinyInteger('publigo_sent')->default(0)->comment('Czy zamówienie zostało wysłane do Publigo przez button "Dodaj zamówienie PUBLIGO" (0=nie wysłane, 1=wysłane) - zapobiega duplikatom');
            }
            if (!Schema::connection('mysql_certgen')->hasColumn('zamowienia_FORM', 'publigo_sent_at')) {
                $table->timestamp('publigo_sent_at')->nullable()->comment('Data i godzina wysłania zamówienia do Publigo przez API - ustawiane automatycznie po udanym wysłaniu');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql_certgen')->table('zamowienia_FORM', function (Blueprint $table) {
            $table->dropColumn(['publigo_sent', 'publigo_sent_at']);
        });
    }
};