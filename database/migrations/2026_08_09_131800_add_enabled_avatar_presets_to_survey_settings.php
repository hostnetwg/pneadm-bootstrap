<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Które awatary przykładowe pokazywać w formularzu rekomendacji (pnedu).
 * null w kolumnie = użyj zestawu profesjonalnego z kodu.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('survey_settings')) {
            return;
        }

        if (! Schema::hasColumn('survey_settings', 'enabled_avatar_presets')) {
            Schema::table('survey_settings', function (Blueprint $table) {
                $table->json('enabled_avatar_presets')->nullable()->after('allow_multiple_responses');
            });
        }

        $defaults = json_encode([
            'woman-straight-brown',
            'woman-straight-blonde',
            'woman-bob-black',
            'woman-curly-auburn',
            'woman-short-waved',
            'woman-long-glasses',
            'woman-bob-glasses',
            'woman-mia',
            'woman-hijab',
            'man-short-flat',
            'man-short-round',
            'man-shaggy',
            'man-glasses',
            'man-beard',
            'man-beard-glasses',
            'man-gray-beard',
            'man-bald',
            'man-bald-beard',
        ]);

        DB::table('survey_settings')
            ->where('id', 1)
            ->whereNull('enabled_avatar_presets')
            ->update(['enabled_avatar_presets' => $defaults]);
    }

    public function down(): void
    {
        if (Schema::hasTable('survey_settings') && Schema::hasColumn('survey_settings', 'enabled_avatar_presets')) {
            Schema::table('survey_settings', function (Blueprint $table) {
                $table->dropColumn('enabled_avatar_presets');
            });
        }
    }
};
