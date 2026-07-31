<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            $table->string('ifirma_invoice_id', 64)
                ->nullable()
                ->after('invoice_number')
                ->comment('Wewnętrzny Identyfikator / FakturaId dokumentu w iFirma');

            $table->index('ifirma_invoice_id', 'idx_ifirma_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            $table->dropIndex('idx_ifirma_invoice_id');
            $table->dropColumn('ifirma_invoice_id');
        });
    }
};
