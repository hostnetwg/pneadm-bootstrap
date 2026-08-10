<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wyróżnione rekomendacje zawsze na górze homepage pnedu.pl.
 * Kolejność wśród wyróżnionych: display_order ASC.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('survey_testimonials')) {
            return;
        }

        if (! Schema::hasColumn('survey_testimonials', 'is_featured')) {
            Schema::table('survey_testimonials', function (Blueprint $table) {
                $table->boolean('is_featured')->default(false)->after('is_published');
                $table->index(['is_published', 'is_featured', 'display_order'], 'survey_testimonials_homepage_order_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('survey_testimonials') || ! Schema::hasColumn('survey_testimonials', 'is_featured')) {
            return;
        }

        Schema::table('survey_testimonials', function (Blueprint $table) {
            $table->dropIndex('survey_testimonials_homepage_order_idx');
            $table->dropColumn('is_featured');
        });
    }
};
