<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debt_cases', function (Blueprint $table) {
            $table->string('product_name', 500)->nullable()->after('amount_gross');
        });

        DB::table('debt_cases')
            ->join('form_orders', 'form_orders.id', '=', 'debt_cases.form_order_id')
            ->whereNull('debt_cases.product_name')
            ->whereNotNull('form_orders.product_name')
            ->where('form_orders.product_name', '!=', '')
            ->update([
                'debt_cases.product_name' => DB::raw('form_orders.product_name'),
            ]);
    }

    public function down(): void
    {
        Schema::table('debt_cases', function (Blueprint $table) {
            $table->dropColumn('product_name');
        });
    }
};
