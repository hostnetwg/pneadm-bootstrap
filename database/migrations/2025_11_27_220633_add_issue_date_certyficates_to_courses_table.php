<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Używamy surowego SQL, aby dodać komentarz do kolumny w MySQL
        DB::statement("ALTER TABLE courses ADD COLUMN issue_date_certyficates DATE NULL COMMENT 'Globalna data wydania zaświadczeń dla tego szkolenia' AFTER end_date");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('issue_date_certyficates');
        });
    }
};
