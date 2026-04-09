<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->table('form_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('course_price_variant_id')->nullable()->after('publigo_price_id');
            $table->foreign('course_price_variant_id')
                ->references('id')
                ->on('course_price_variants')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('form_orders', function (Blueprint $table) {
            $table->dropForeign(['course_price_variant_id']);
            $table->dropColumn('course_price_variant_id');
        });
    }
};
