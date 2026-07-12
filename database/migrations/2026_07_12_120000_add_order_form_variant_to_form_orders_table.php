<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('form_orders')) {
            return;
        }

        Schema::table('form_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('form_orders', 'order_form_variant')) {
                $table->string('order_form_variant', 10)
                    ->nullable()
                    ->after('submission_source')
                    ->comment('Wersja publicznego formularza PNEDU przy złożeniu: legacy|v2');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('form_orders')) {
            return;
        }

        Schema::table('form_orders', function (Blueprint $table) {
            if (Schema::hasColumn('form_orders', 'order_form_variant')) {
                $table->dropColumn('order_form_variant');
            }
        });
    }
};
