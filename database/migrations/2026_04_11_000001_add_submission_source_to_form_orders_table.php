<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kanał zapisu zamówienia (rozróżnienie PNEDU vs panel vs historia).
     */
    public function up(): void
    {
        Schema::connection('mysql')->table('form_orders', function (Blueprint $table) {
            $table->string('submission_source', 32)
                ->nullable()
                ->after('payment_status')
                ->comment('Skąd powstało zamówienie: pnedu_order_form = formularz publiczny na PNEDU (odroczona i online); pneadm_manual = utworzenie w panelu pneadm; NULL = wpis historyczny lub zapis spoza tych ścieżek (np. stary formularz zdalna-lekcja, import).');
            $table->index('submission_source', 'idx_form_orders_submission_source');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('form_orders', function (Blueprint $table) {
            $table->dropIndex('idx_form_orders_submission_source');
            $table->dropColumn('submission_source');
        });
    }
};
