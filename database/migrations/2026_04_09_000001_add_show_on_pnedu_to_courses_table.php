<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('show_on_pnedu')
                ->default(false)
                ->after('source_id_old');
        });

        // Backfill: preserve current homepage visibility behavior in pnedu.
        DB::table('courses')
            ->whereIn('source_id_old', ['certgen_Publigo', 'BD:Certgen-education'])
            ->update(['show_on_pnedu' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('show_on_pnedu');
        });
    }
};
