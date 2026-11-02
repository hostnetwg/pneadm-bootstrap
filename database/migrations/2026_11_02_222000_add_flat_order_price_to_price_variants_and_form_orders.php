<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_price_variants', function (Blueprint $table) {
            $table->boolean('flat_order_price')->default(false)->after('price');
        });

        Schema::table('form_orders', function (Blueprint $table) {
            $table->boolean('flat_order_price')->default(false)->after('course_price_variant_id');
        });
    }

    public function down(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            $table->dropColumn('flat_order_price');
        });

        Schema::table('course_price_variants', function (Blueprint $table) {
            $table->dropColumn('flat_order_price');
        });
    }
};
