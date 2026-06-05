<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('participants')
            ->whereNotNull('deleted_at')
            ->whereNotNull('email_normalized')
            ->update(['email_normalized' => null]);
    }

    public function down(): void
    {
        // Nie odtwarzamy email_normalized u rekordów usuniętych.
    }
};
