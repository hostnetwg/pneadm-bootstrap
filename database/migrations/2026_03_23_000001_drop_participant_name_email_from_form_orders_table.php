<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Usuwa zduplikowane pola uczestnika z form_orders – źródłem prawdy jest form_order_participants.
     */
    public function up(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            if (Schema::hasColumn('form_orders', 'participant_name')) {
                $table->dropColumn('participant_name');
            }
            if (Schema::hasColumn('form_orders', 'participant_email')) {
                $table->dropColumn('participant_email');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('form_orders', 'participant_name')) {
                $table->string('participant_name', 255)->nullable()->after('publigo_sent_at');
            }
            if (! Schema::hasColumn('form_orders', 'participant_email')) {
                $table->string('participant_email', 255)->nullable()->after('participant_name');
            }
        });
    }
};
