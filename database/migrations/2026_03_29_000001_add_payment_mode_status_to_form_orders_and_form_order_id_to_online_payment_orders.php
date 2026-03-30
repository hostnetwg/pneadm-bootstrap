<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tryb rozliczenia (faktura z odroczeniem vs bramka) oraz status cyklu płatności;
     * powiązanie rekordu płatności online z zamówieniem formularza.
     */
    public function up(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('form_orders', 'payment_mode')) {
                $table->string('payment_mode', 32)
                    ->nullable()
                    ->after('invoice_payment_delay')
                    ->comment('Tryb rozliczenia: deferred_invoice = faktura z odroczonym terminem (Wyślij zamówienie); online_gateway = natychmiastowa płatność przez bramkę (Przejdź do płatności online).');
            }
            if (! Schema::hasColumn('form_orders', 'payment_status')) {
                $table->string('payment_status', 32)
                    ->nullable()
                    ->after('payment_mode')
                    ->comment('Status płatności/etapu: dla deferred_invoice zwykle submitted; dla online_gateway m.in. awaiting_payment, paid, cancelled, failed (zsynchronizowane z online_payment_orders przy powiązaniu).');
            }
        });

        if (Schema::hasColumn('form_orders', 'payment_mode')) {
            DB::table('form_orders')
                ->whereNull('payment_mode')
                ->update([
                    'payment_mode' => 'deferred_invoice',
                    'payment_status' => 'submitted',
                ]);
        }

        Schema::table('form_orders', function (Blueprint $table) {
            if (Schema::hasColumn('form_orders', 'payment_mode')) {
                $table->index('payment_mode', 'idx_form_orders_payment_mode');
            }
            if (Schema::hasColumn('form_orders', 'payment_status')) {
                $table->index('payment_status', 'idx_form_orders_payment_status');
            }
        });

        Schema::table('online_payment_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('online_payment_orders', 'form_order_id')) {
                $table->unsignedBigInteger('form_order_id')
                    ->nullable()
                    ->after('id')
                    ->comment('Powiązanie z form_orders.id (zamówienie z formularza www); NULL = rekord tylko pod bramkę (np. starszy flow).');
                $table->index('form_order_id', 'idx_online_payment_orders_form_order_id');
                $table->foreign('form_order_id')
                    ->references('id')
                    ->on('form_orders')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('online_payment_orders', function (Blueprint $table) {
            if (Schema::hasColumn('online_payment_orders', 'form_order_id')) {
                $table->dropForeign(['form_order_id']);
                $table->dropIndex('idx_online_payment_orders_form_order_id');
                $table->dropColumn('form_order_id');
            }
        });

        Schema::table('form_orders', function (Blueprint $table) {
            if (Schema::hasColumn('form_orders', 'payment_mode')) {
                $table->dropIndex('idx_form_orders_payment_mode');
            }
            if (Schema::hasColumn('form_orders', 'payment_status')) {
                $table->dropIndex('idx_form_orders_payment_status');
            }
        });

        Schema::table('form_orders', function (Blueprint $table) {
            if (Schema::hasColumn('form_orders', 'payment_status')) {
                $table->dropColumn('payment_status');
            }
            if (Schema::hasColumn('form_orders', 'payment_mode')) {
                $table->dropColumn('payment_mode');
            }
        });
    }
};
