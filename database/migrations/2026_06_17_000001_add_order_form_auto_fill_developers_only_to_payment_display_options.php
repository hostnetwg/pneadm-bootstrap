<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auto-wypełnianie formularza zamówienia danymi testowymi — tylko dla wybranych kont deweloperskich na pnedu.pl.
     */
    public function up(): void
    {
        Schema::table('payment_display_options', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_display_options', 'order_form_auto_fill_test_data_developers_only')) {
                $table->boolean('order_form_auto_fill_test_data_developers_only')
                    ->default(false)
                    ->after('order_form_auto_fill_test_data_enabled_at')
                    ->comment('Auto-wypełnianie formularza danymi testowymi tylko dla kont deweloperskich (zalogowani na pnedu.pl)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_display_options', function (Blueprint $table) {
            if (Schema::hasColumn('payment_display_options', 'order_form_auto_fill_test_data_developers_only')) {
                $table->dropColumn('order_form_auto_fill_test_data_developers_only');
            }
        });
    }
};
