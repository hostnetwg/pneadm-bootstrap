<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabela revenue_records - przechowuje dane przychodów księgowych na dany miesiąc
     */
    public function up(): void
    {
        Schema::create('revenue_records', function (Blueprint $table) {
            $table->id();
            
            // Okres rozliczeniowy
            $table->year('year')->comment('Rok rozliczeniowy');
            $table->tinyInteger('month')->comment('Miesiąc (1-12)');
            
            // Dane przychodu
            $table->decimal('amount', 15, 2)->comment('Kwota przychodu');
            $table->text('notes')->nullable()->comment('Opcjonalne notatki');
            
            // Źródło danych (przygotowanie na przyszłą integrację z iFirma)
            $table->string('source', 50)->default('manual')->comment('Źródło danych: manual, ifirma, itp.');
            
            // Kto wprowadził dane
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->comment('Użytkownik, który wprowadził dane');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indeksy dla szybkiego wyszukiwania
            $table->index(['year', 'month']);
            
            // Unikalność: jeden rekord na miesiąc
            $table->unique(['year', 'month'], 'revenue_records_year_month_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revenue_records');
    }
};
