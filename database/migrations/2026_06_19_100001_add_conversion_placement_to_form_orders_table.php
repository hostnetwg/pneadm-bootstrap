<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Miejsce konwersji (np. panel klienta → Aktualna oferta) — osobno od fb_source / kampanii reklamowej.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            $table->string('conversion_placement', 50)->nullable()->after('fb_source');
        });
    }

    public function down(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            $table->dropColumn('conversion_placement');
        });
    }
};
