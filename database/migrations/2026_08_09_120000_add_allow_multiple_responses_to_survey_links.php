<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Limit wielokrotnego wypełnienia per ankieta (link).
 * survey_settings.allow_multiple_responses = domyślna wartość przy tworzeniu.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('course_survey_links') && ! Schema::hasColumn('course_survey_links', 'allow_multiple_responses')) {
            Schema::table('course_survey_links', function (Blueprint $table) {
                $table->boolean('allow_multiple_responses')->default(false)->after('is_anonymous');
            });
        }

        if (Schema::hasTable('surveys') && ! Schema::hasColumn('surveys', 'allow_multiple_responses')) {
            Schema::table('surveys', function (Blueprint $table) {
                $table->boolean('allow_multiple_responses')->default(false)->after('is_anonymous');
            });
        }

        // Zachowaj dotychczasowe zachowanie: skopiuj aktualne ustawienie globalne na istniejące rekordy.
        if (Schema::hasTable('survey_settings')) {
            $global = (bool) DB::table('survey_settings')->where('id', 1)->value('allow_multiple_responses');
            if ($global && Schema::hasColumn('course_survey_links', 'allow_multiple_responses')) {
                DB::table('course_survey_links')->update(['allow_multiple_responses' => true]);
            }
            if ($global && Schema::hasColumn('surveys', 'allow_multiple_responses')) {
                DB::table('surveys')->update(['allow_multiple_responses' => true]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('course_survey_links') && Schema::hasColumn('course_survey_links', 'allow_multiple_responses')) {
            Schema::table('course_survey_links', function (Blueprint $table) {
                $table->dropColumn('allow_multiple_responses');
            });
        }

        if (Schema::hasTable('surveys') && Schema::hasColumn('surveys', 'allow_multiple_responses')) {
            Schema::table('surveys', function (Blueprint $table) {
                $table->dropColumn('allow_multiple_responses');
            });
        }
    }
};
