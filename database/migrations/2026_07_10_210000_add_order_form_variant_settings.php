<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_display_options', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_display_options', 'default_signup_order_form_variant')) {
                $table->string('default_signup_order_form_variant', 10)
                    ->default('legacy')
                    ->after('show_order_form_v2')
                    ->comment('Domyślna wersja formularza dla przycisku Zapisz się: legacy|v2');
            }
        });

        Schema::table('marketing_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('marketing_campaigns', 'order_form_variant')) {
                $table->string('order_form_variant', 10)
                    ->default('legacy')
                    ->after('landing_target')
                    ->comment('Wersja formularza zamówienia dla landing_target=order_form: legacy|v2');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_display_options', function (Blueprint $table) {
            if (Schema::hasColumn('payment_display_options', 'default_signup_order_form_variant')) {
                $table->dropColumn('default_signup_order_form_variant');
            }
        });

        Schema::table('marketing_campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('marketing_campaigns', 'order_form_variant')) {
                $table->dropColumn('order_form_variant');
            }
        });
    }
};
