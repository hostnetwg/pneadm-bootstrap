<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course_online_details')) {
            return;
        }

        if (! Schema::hasColumn('course_online_details', 'clickmeeting_join_enabled')
            || ! Schema::hasColumn('course_online_details', 'embed_on_pnedu')) {
            return;
        }

        // Radio: dokładnie jedna opcja. Preferuj embed, gdy był włączony.
        DB::table('course_online_details')
            ->where('embed_on_pnedu', true)
            ->update(['clickmeeting_join_enabled' => false]);

        DB::table('course_online_details')
            ->where('embed_on_pnedu', false)
            ->update(['clickmeeting_join_enabled' => true]);
    }

    public function down(): void
    {
        // Bez cofania danych — flagi pozostają.
    }
};
