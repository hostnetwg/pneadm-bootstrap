<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_display_options', function (Blueprint $table) {
            $table->boolean('show_order_form_v2')
                ->default(false)
                ->after('show_order_form')
                ->comment('Widoczność nowego testowego formularza zamówienia V2');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('payment_display_options', 'show_order_form_v2')) {
            return;
        }

        Schema::table('payment_display_options', function (Blueprint $table) {
            $table->dropColumn('show_order_form_v2');
        });
    }
};
