<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Offset otwarcia ankiety: dozwolone wartości ujemne (przed planowanym końcem szkolenia).
 * Domyślnie −2 h.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('survey_settings')) {
            return;
        }

        Schema::table('survey_settings', function (Blueprint $table) {
            $table->integer('auto_open_offset_hours')->default(-2)->change();
        });

        DB::table('survey_settings')
            ->where('id', 1)
            ->update(['auto_open_offset_hours' => -2]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('survey_settings')) {
            return;
        }

        DB::table('survey_settings')
            ->where('auto_open_offset_hours', '<', 0)
            ->update(['auto_open_offset_hours' => 0]);

        Schema::table('survey_settings', function (Blueprint $table) {
            $table->unsignedInteger('auto_open_offset_hours')->default(0)->change();
        });
    }
};
