<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            $table->timestamp('online_payment_recovery_sent_at')
                ->nullable()
                ->after('payment_status')
                ->comment('Kiedy wysłano e-mail recovery porzuconej płatności online (Etap 3).');
        });
    }

    public function down(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            $table->dropColumn('online_payment_recovery_sent_at');
        });
    }
};
