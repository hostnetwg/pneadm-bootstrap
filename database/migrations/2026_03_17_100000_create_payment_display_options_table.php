<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ustawienia widoczności opcji płatności/zamawiania na pnedu.pl (strona kursu).
     * Tabela w bazie pneadm – odczyt także z aplikacji pnedu.
     */
    public function up(): void
    {
        Schema::create('payment_display_options', function (Blueprint $table) {
            $table->id();
            $table->boolean('show_pay_publigo')->default(true)->comment('Zapłać online PUBLIGO');
            $table->boolean('show_pay_online')->default(true)->comment('Zapłać online');
            $table->boolean('show_deferred_order')->default(true)->comment('Formularz zamówienia z odroczonym terminem płatności');
            $table->boolean('show_order_form')->default(true)->comment('Formularz zamówienia');
            $table->boolean('show_order_form_alt')->default(true)->comment('Alternatywny formularz zamówienia');
            $table->timestamps();
        });

        DB::table('payment_display_options')->insert([
            'show_pay_publigo' => true,
            'show_pay_online' => true,
            'show_deferred_order' => true,
            'show_order_form' => true,
            'show_order_form_alt' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_display_options');
    }
};
