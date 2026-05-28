<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payment_display_options', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_display_options', 'order_form_auto_fill_test_data_enabled_at')) {
                $table->timestamp('order_form_auto_fill_test_data_enabled_at')
                    ->nullable()
                    ->after('order_form_auto_fill_test_data')
                    ->comment('Moment włączenia opcji auto-uzupełniania formularza danymi testowymi');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_display_options', function (Blueprint $table) {
            if (Schema::hasColumn('payment_display_options', 'order_form_auto_fill_test_data_enabled_at')) {
                $table->dropColumn('order_form_auto_fill_test_data_enabled_at');
            }
        });
    }
};
