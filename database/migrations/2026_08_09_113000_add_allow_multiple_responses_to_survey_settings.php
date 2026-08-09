<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Czy zezwalać na wielokrotne wypełnienie tej samej ankiety natywnej (domyślnie nie).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('survey_settings')) {
            return;
        }

        Schema::table('survey_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('survey_settings', 'allow_multiple_responses')) {
                $table->boolean('allow_multiple_responses')->default(false)->after('default_is_anonymous');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('survey_settings')) {
            return;
        }

        Schema::table('survey_settings', function (Blueprint $table) {
            if (Schema::hasColumn('survey_settings', 'allow_multiple_responses')) {
                $table->dropColumn('allow_multiple_responses');
            }
        });
    }
};
