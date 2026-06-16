<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Domyślne utm_content per typ źródła — rozróżnienie taktyk w GA4 (prospecting vs remarketing itd.)
 * przy zachowaniu utm_source=facebook i utm_medium=paid dla płatnych kanałów Meta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_source_types', function (Blueprint $table) {
            $table->string('default_utm_content', 100)->nullable()->after('default_utm_medium');
        });

        $defaultsBySlug = [
            'facebook' => 'prospecting',
            'remarketing' => 'remarketing',
            'organic' => 'organic',
            'tiktok' => 'prospecting',
            'training' => 'webinar',
        ];

        foreach ($defaultsBySlug as $slug => $content) {
            DB::table('marketing_source_types')
                ->where('slug', $slug)
                ->update([
                    'default_utm_content' => $content,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('marketing_source_types', function (Blueprint $table) {
            $table->dropColumn('default_utm_content');
        });
    }
};
