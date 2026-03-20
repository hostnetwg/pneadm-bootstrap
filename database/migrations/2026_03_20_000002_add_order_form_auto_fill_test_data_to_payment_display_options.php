<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dodaje flagę automatycznego wypełniania formularza zamówienia danymi testowymi.
     * Kontrolowana z panelu Ustawienia → Zakupy pnedu.pl.
     */
    public function up(): void
    {
        Schema::table('payment_display_options', function (Blueprint $table) {
            $table->boolean('order_form_auto_fill_test_data')->default(false)->after('show_order_form_alt')
                ->comment('Automatyczne wypełnianie formularza zamówienia danymi testowymi (tylko do testów)');
        });

    }

    public function down(): void
    {
        Schema::table('payment_display_options', function (Blueprint $table) {
            $table->dropColumn('order_form_auto_fill_test_data');
        });
    }
};
