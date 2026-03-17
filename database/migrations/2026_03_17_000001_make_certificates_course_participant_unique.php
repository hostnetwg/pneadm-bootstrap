<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Najpierw usuń ewentualne duplikaty (course_id + participant_id),
        // bo inaczej dodanie indeksu UNIQUE się nie powiedzie.
        // Zostawiamy rekord o najmniejszym id, resztę usuwamy.
        $duplicates = DB::table('certificates')
            ->select('course_id', 'participant_id', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('course_id', 'participant_id')
            ->having('cnt', '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('certificates')
                ->where('course_id', $dup->course_id)
                ->where('participant_id', $dup->participant_id)
                ->where('id', '!=', $dup->keep_id)
                ->delete();
        }

        Schema::table('certificates', function (Blueprint $table) {
            $table->unique(['course_id', 'participant_id'], 'certificates_course_participant_unique');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropUnique('certificates_course_participant_unique');
        });
    }
};

