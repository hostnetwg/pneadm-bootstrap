<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Po regeneracji zestawu awatarów (16 nauczycielskich) — zresetuj listę włączonych.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('survey_settings') || ! Schema::hasColumn('survey_settings', 'enabled_avatar_presets')) {
            return;
        }

        $keys = [
            'woman-straight-brown',
            'woman-straight-blonde',
            'woman-bob-dark',
            'woman-bob-glasses',
            'woman-long-glasses',
            'woman-curly-brown',
            'woman-short-waved',
            'woman-hijab',
            'man-short-flat',
            'man-short-round',
            'man-caesar',
            'man-glasses',
            'man-beard',
            'man-beard-glasses',
            'man-gray',
            'man-bald-beard',
        ];

        DB::table('survey_settings')
            ->where('id', 1)
            ->update(['enabled_avatar_presets' => json_encode($keys)]);

        // Zaktualizuj zapisane rekomendacje ze starymi kluczami (najczęstsze mapowania).
        $map = [
            'woman-bob-black' => 'woman-bob-dark',
            'woman-curly-auburn' => 'woman-curly-brown',
            'woman-bun-red' => 'woman-bob-dark',
            'woman-big-hair' => 'woman-curly-brown',
            'woman-mia' => 'woman-long-glasses',
            'woman-fro' => 'woman-curly-brown',
            'man-blonde-sides' => 'man-caesar',
            'man-shaggy' => 'man-glasses',
            'man-gray-beard' => 'man-gray',
            'man-moustache' => 'man-beard',
            'man-dreads' => 'man-short-flat',
            'man-bald' => 'man-bald-beard',
            'teacher-f-1' => 'woman-straight-brown',
            'teacher-f-2' => 'woman-bob-dark',
            'teacher-m-1' => 'man-short-flat',
            'teacher-m-2' => 'man-beard',
            'director-f-1' => 'woman-long-glasses',
            'director-m-1' => 'man-beard-glasses',
            'neutral-1' => 'man-caesar',
            'neutral-2' => 'woman-short-waved',
        ];

        if (Schema::hasTable('survey_testimonials') && Schema::hasColumn('survey_testimonials', 'avatar_preset')) {
            foreach ($map as $from => $to) {
                DB::table('survey_testimonials')
                    ->where('avatar_preset', $from)
                    ->update(['avatar_preset' => $to]);
            }
        }
    }

    public function down(): void
    {
        // bez rollbacku danych awatarów
    }
};
