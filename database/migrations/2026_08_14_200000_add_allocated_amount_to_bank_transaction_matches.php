<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transaction_matches', function (Blueprint $table) {
            $table->decimal('allocated_amount', 12, 2)
                ->nullable()
                ->after('status')
                ->comment('Kwota z przelewu przypisana do tej FV/sprawy (podział jednego wpływu)');
        });

        // Backfill: zaakceptowane / zignorowane bez alokacji = pełna kwota przelewu
        DB::statement('
            UPDATE bank_transaction_matches m
            INNER JOIN bank_transactions t ON t.id = m.bank_transaction_id
            SET m.allocated_amount = t.amount
            WHERE m.allocated_amount IS NULL
              AND m.status IN ("accepted", "ignored")
        ');
    }

    public function down(): void
    {
        Schema::table('bank_transaction_matches', function (Blueprint $table) {
            $table->dropColumn('allocated_amount');
        });
    }
};
