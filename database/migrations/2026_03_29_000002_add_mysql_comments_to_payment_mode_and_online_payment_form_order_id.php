<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Komentarze MySQL dla payment_mode, payment_status (form_orders) oraz form_order_id (online_payment_orders).
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasColumn('form_orders', 'payment_mode')) {
            $c1 = 'Tryb rozliczenia: deferred_invoice = faktura z odroczonym terminem (formularz „Wyślij zamówienie”); online_gateway = natychmiastowa płatność przez bramkę (formularz „Przejdź do płatności online”).';
            DB::statement('ALTER TABLE form_orders MODIFY COLUMN payment_mode VARCHAR(32) NULL COMMENT '.$this->quote($c1));
        }

        if (Schema::hasColumn('form_orders', 'payment_status')) {
            $c2 = 'Status płatności / etapu: dla deferred_invoice zwykle submitted (złożone); dla online_gateway m.in. awaiting_payment, paid, cancelled, failed — zsynchronizowany z online_payment_orders przy powiązaniu.';
            DB::statement('ALTER TABLE form_orders MODIFY COLUMN payment_status VARCHAR(32) NULL COMMENT '.$this->quote($c2));
        }

        if (Schema::hasColumn('online_payment_orders', 'form_order_id')) {
            $c3 = 'FK do form_orders.id: zamówienie z formularza www (PNEDU); NULL = rekord tylko pod bramkę (np. starszy flow).';
            DB::statement('ALTER TABLE online_payment_orders MODIFY COLUMN form_order_id BIGINT UNSIGNED NULL COMMENT '.$this->quote($c3));
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasColumn('form_orders', 'payment_mode')) {
            DB::statement('ALTER TABLE form_orders MODIFY COLUMN payment_mode VARCHAR(32) NULL');
        }

        if (Schema::hasColumn('form_orders', 'payment_status')) {
            DB::statement('ALTER TABLE form_orders MODIFY COLUMN payment_status VARCHAR(32) NULL');
        }

        if (Schema::hasColumn('online_payment_orders', 'form_order_id')) {
            DB::statement('ALTER TABLE online_payment_orders MODIFY COLUMN form_order_id BIGINT UNSIGNED NULL');
        }
    }

    private function quote(string $value): string
    {
        return DB::getPdo()->quote($value);
    }
};
