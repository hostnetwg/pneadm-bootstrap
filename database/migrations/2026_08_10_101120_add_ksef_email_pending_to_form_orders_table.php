<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            $table->boolean('ksef_email_pending')
                ->default(false)
                ->after('ksef_error')
                ->comment('Intencja: wyślij FV mailem przez iFirma po uzyskaniu NumerKSeF (red flow / Odśwież KSeF)');
        });
    }

    public function down(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            $table->dropColumn('ksef_email_pending');
        });
    }
};
