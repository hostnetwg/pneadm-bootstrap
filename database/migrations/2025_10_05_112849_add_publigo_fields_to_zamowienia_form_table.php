<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            // Dodanie pól Publigo do tabeli zamowienia_FORM w bazie certgen.
            // Jeśli certgen nie jest dostępny w danym środowisku, pomijamy migrację.
            if (!Schema::connection('mysql_certgen')->hasTable('zamowienia_FORM')) {
                return;
            }

            Schema::connection('mysql_certgen')->table('zamowienia_FORM', function (Blueprint $table) {
                if (!Schema::connection('mysql_certgen')->hasColumn('zamowienia_FORM', 'publigo_sent')) {
                    $table->tinyInteger('publigo_sent')->default(0)->comment('Czy zamówienie zostało wysłane do Publigo przez button "Dodaj zamówienie PUBLIGO" (0=nie wysłane, 1=wysłane) - zapobiega duplikatom');
                }
                if (!Schema::connection('mysql_certgen')->hasColumn('zamowienia_FORM', 'publigo_sent_at')) {
                    $table->timestamp('publigo_sent_at')->nullable()->comment('Data i godzina wysłania zamówienia do Publigo przez API - ustawiane automatycznie po udanym wysłaniu');
                }
            });
        } catch (QueryException) {
            // Połączenie mysql_certgen może nie istnieć lokalnie.
            return;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            if (!Schema::connection('mysql_certgen')->hasTable('zamowienia_FORM')) {
                return;
            }

            Schema::connection('mysql_certgen')->table('zamowienia_FORM', function (Blueprint $table) {
                if (Schema::connection('mysql_certgen')->hasColumn('zamowienia_FORM', 'publigo_sent')) {
                    $table->dropColumn('publigo_sent');
                }
                if (Schema::connection('mysql_certgen')->hasColumn('zamowienia_FORM', 'publigo_sent_at')) {
                    $table->dropColumn('publigo_sent_at');
                }
            });
        } catch (QueryException) {
            return;
        }
    }
};