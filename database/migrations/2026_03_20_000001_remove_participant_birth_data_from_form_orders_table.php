<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Usuwa nieużywane pola participant_birth_date i participant_birth_place z form_orders.
     * Dane urodzenia uczestników są przechowywane w tabeli participants (dla kursów).
     */
    public function up(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            if (Schema::hasColumn('form_orders', 'participant_birth_date')) {
                $table->dropColumn('participant_birth_date');
            }
            if (Schema::hasColumn('form_orders', 'participant_birth_place')) {
                $table->dropColumn('participant_birth_place');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            $table->date('participant_birth_date')->nullable()->after('participant_email');
            $table->string('participant_birth_place', 255)->nullable()->after('participant_birth_date');
        });
    }
};
