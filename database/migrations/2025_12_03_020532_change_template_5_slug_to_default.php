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
        // Sprawdź czy szablon ID=5 istnieje i ma slug 'default-kopia'
        $template = DB::table('certificate_templates')
            ->where('id', 5)
            ->where('slug', 'default-kopia')
            ->first();
        
        if ($template) {
            // Sprawdź czy istnieje już szablon z slug 'default' (różny od ID=5)
            $existingDefault = DB::table('certificate_templates')
                ->where('slug', 'default')
                ->where('id', '!=', 5)
                ->first();
            
            if ($existingDefault) {
                // Jeśli istnieje, zmień jego slug na 'default-old-{id}'
                DB::table('certificate_templates')
                    ->where('id', $existingDefault->id)
                    ->update(['slug' => 'default-old-' . $existingDefault->id]);
            }
            
            // Zmień slug szablonu ID=5 na 'default'
            DB::table('certificate_templates')
                ->where('id', 5)
                ->update(['slug' => 'default']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Przywróć slug 'default-kopia' dla szablonu ID=5
        DB::table('certificate_templates')
            ->where('id', 5)
            ->where('slug', 'default')
            ->update(['slug' => 'default-kopia']);
        
        // Przywróć slug 'default' dla innych szablonów (jeśli były zmienione)
        $oldDefault = DB::table('certificate_templates')
            ->where('slug', 'like', 'default-old-%')
            ->first();
        
        if ($oldDefault) {
            // Wyciągnij ID z slug 'default-old-{id}'
            $oldId = str_replace('default-old-', '', $oldDefault->slug);
            DB::table('certificate_templates')
                ->where('id', $oldId)
                ->update(['slug' => 'default']);
        }
    }
};
