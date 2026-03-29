<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dopisuje komentarze kolumn w MySQL (środowiska, w których uruchomiono już migrację 2026_03_28_000001 bez ->comment()).
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        if (! Schema::hasColumn('form_orders', 'pnedu_provisioned_at')) {
            return;
        }

        $c1 = 'Data i czas przyznania dostępu w PNEDU (przycisk „Dodaj tylko do PNEDU”). NULL = akcja jeszcze nie wykonana.';
        $c2 = 'Czy konto w pnedu.users (e-mail uczestnika) istniało przed akcją: 1=tak, 0=utworzono nowe konto, NULL=brak akcji.';

        DB::statement('ALTER TABLE form_orders MODIFY COLUMN pnedu_provisioned_at TIMESTAMP NULL DEFAULT NULL COMMENT '.$this->quote($c1));
        DB::statement('ALTER TABLE form_orders MODIFY COLUMN pnedu_user_existed_before TINYINT(1) NULL DEFAULT NULL COMMENT '.$this->quote($c2));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        if (! Schema::hasColumn('form_orders', 'pnedu_provisioned_at')) {
            return;
        }

        DB::statement('ALTER TABLE form_orders MODIFY COLUMN pnedu_provisioned_at TIMESTAMP NULL DEFAULT NULL');
        DB::statement('ALTER TABLE form_orders MODIFY COLUMN pnedu_user_existed_before TINYINT(1) NULL DEFAULT NULL');
    }

    private function quote(string $value): string
    {
        return DB::getPdo()->quote($value);
    }
};
