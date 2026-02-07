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
        Schema::table('form_orders', function (Blueprint $table) {
            $table->date('participant_birth_date')->nullable()->after('participant_email')->comment('Data urodzenia uczestnika - wymagane do wystawienia zaświadczeń');
            $table->string('participant_birth_place', 255)->nullable()->after('participant_birth_date')->comment('Miejsce urodzenia uczestnika - wymagane do wystawienia zaświadczeń');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            $table->dropColumn(['participant_birth_date', 'participant_birth_place']);
        });
    }
};
