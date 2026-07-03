<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('form_orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('status_completed');
                $table->index('cancelled_at', 'idx_form_orders_cancelled_at');
            }
            if (! Schema::hasColumn('form_orders', 'cancelled_reason')) {
                $table->string('cancelled_reason', 255)->nullable()->after('cancelled_at');
            }
            if (! Schema::hasColumn('form_orders', 'cancelled_by')) {
                $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_reason');
                $table->foreign('cancelled_by', 'fk_form_orders_cancelled_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        });

        // Jednorazowy backfill: historyczne „zakończone bez FV” traktujemy jako anulowanie.
        if (Schema::hasColumn('form_orders', 'cancelled_at')) {
            DB::table('form_orders')
                ->whereNull('cancelled_at')
                ->where('status_completed', 1)
                ->where(function ($q) {
                    $q->whereNull('invoice_number')
                        ->orWhere('invoice_number', '')
                        ->orWhere('invoice_number', '0');
                })
                ->update([
                    'cancelled_at' => DB::raw('COALESCE(updated_at, created_at, NOW())'),
                    'cancelled_reason' => 'legacy: status_completed bez faktury',
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            if (Schema::hasColumn('form_orders', 'cancelled_by')) {
                $table->dropForeign('fk_form_orders_cancelled_by');
                $table->dropColumn('cancelled_by');
            }
            if (Schema::hasColumn('form_orders', 'cancelled_reason')) {
                $table->dropColumn('cancelled_reason');
            }
            if (Schema::hasColumn('form_orders', 'cancelled_at')) {
                $table->dropIndex('idx_form_orders_cancelled_at');
                $table->dropColumn('cancelled_at');
            }
        });
    }
};
