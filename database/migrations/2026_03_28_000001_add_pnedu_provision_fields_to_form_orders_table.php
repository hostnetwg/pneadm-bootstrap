<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Oznaczenie realizacji „Dodaj tylko do PNEDU” (wariant A): data + czy konto users w pnedu już istniało.
     */
    public function up(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('form_orders', 'pnedu_provisioned_at')) {
                $table->timestamp('pnedu_provisioned_at')
                    ->nullable()
                    ->after('publigo_sent_at')
                    ->comment('Data i czas przyznania dostępu w PNEDU (przycisk „Dodaj tylko do PNEDU”). NULL = akcja jeszcze nie wykonana.');
            }
            if (! Schema::hasColumn('form_orders', 'pnedu_user_existed_before')) {
                $table->boolean('pnedu_user_existed_before')
                    ->nullable()
                    ->after('pnedu_provisioned_at')
                    ->comment('Czy konto w pnedu.users (e-mail uczestnika) istniało przed akcją: 1=tak, 0=utworzono nowe konto, NULL=brak akcji.');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            if (Schema::hasColumn('form_orders', 'pnedu_user_existed_before')) {
                $table->dropColumn('pnedu_user_existed_before');
            }
            if (Schema::hasColumn('form_orders', 'pnedu_provisioned_at')) {
                $table->dropColumn('pnedu_provisioned_at');
            }
        });
    }
};
