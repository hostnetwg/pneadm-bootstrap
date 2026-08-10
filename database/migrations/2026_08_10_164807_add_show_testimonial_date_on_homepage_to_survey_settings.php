<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Globalny przełącznik: pokaż datę rekomendacji na homepage pnedu.pl.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('survey_settings')) {
            return;
        }

        if (! Schema::hasColumn('survey_settings', 'show_testimonial_date_on_homepage')) {
            Schema::table('survey_settings', function (Blueprint $table) {
                $table->boolean('show_testimonial_date_on_homepage')->default(false)->after('enabled_avatar_presets');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('survey_settings') && Schema::hasColumn('survey_settings', 'show_testimonial_date_on_homepage')) {
            Schema::table('survey_settings', function (Blueprint $table) {
                $table->dropColumn('show_testimonial_date_on_homepage');
            });
        }
    }
};
