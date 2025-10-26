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
        // Najpierw wypełnij tabelę marketing_source_types
        $sourceTypes = [
            ['name' => 'Facebook', 'slug' => 'facebook', 'description' => 'Reklamy płatne na Facebooku', 'color' => '#1877F2', 'sort_order' => 1],
            ['name' => 'Email', 'slug' => 'email', 'description' => 'Kampanie emailowe', 'color' => '#EA4335', 'sort_order' => 2],
            ['name' => 'Website', 'slug' => 'website', 'description' => 'Strony sprzedażowe i landing pages', 'color' => '#34A853', 'sort_order' => 3],
            ['name' => 'Remarketing', 'slug' => 'remarketing', 'description' => 'Kampanie remarketingowe', 'color' => '#FF6B35', 'sort_order' => 4],
            ['name' => 'Training', 'slug' => 'training', 'description' => 'Szkolenia i webinary', 'color' => '#9C27B0', 'sort_order' => 5],
            ['name' => 'Organic', 'slug' => 'organic', 'description' => 'Posty organiczne i wydarzenia', 'color' => '#607D8B', 'sort_order' => 6],
        ];

        foreach ($sourceTypes as $type) {
            DB::table('marketing_source_types')->insert(array_merge($type, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // Teraz zaktualizuj istniejące kampanie
        $mapping = [
            'facebook' => 1,
            'email' => 2,
            'website' => 3,
            'remarketing' => 4,
            'training' => 5,
            'organic' => 6,
        ];

        foreach ($mapping as $oldType => $newId) {
            DB::table('marketing_campaigns')
                ->where('source_type', $oldType)
                ->update(['source_type_id' => $newId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Przywróć source_type na podstawie source_type_id
        $mapping = [
            1 => 'facebook',
            2 => 'email',
            3 => 'website',
            4 => 'remarketing',
            5 => 'training',
            6 => 'organic',
        ];

        foreach ($mapping as $id => $oldType) {
            DB::table('marketing_campaigns')
                ->where('source_type_id', $id)
                ->update(['source_type' => $oldType]);
        }

        // Usuń dane z marketing_source_types
        DB::table('marketing_source_types')->truncate();
    }
};
