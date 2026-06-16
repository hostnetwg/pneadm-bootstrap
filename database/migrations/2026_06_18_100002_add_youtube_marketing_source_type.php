<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Typ źródła YouTube — opisy filmów, wydarzenia live, linki w kanale.
 * GA4: utm_source=youtube, utm_medium=social, utm_content=video-description (domyślnie).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('marketing_source_types')->where('slug', 'youtube')->exists()) {
            return;
        }

        $sortOrder = (int) DB::table('marketing_source_types')->max('sort_order') + 1;
        $now = now();

        DB::table('marketing_source_types')->insert([
            'name' => 'YouTube — opisy i wydarzenia',
            'slug' => 'youtube',
            'utm_source' => 'youtube',
            'default_utm_medium' => 'social',
            'default_utm_content' => 'video-description',
            'description' => 'Linki w opisach filmów, przy wydarzeniach live i w postach społeczności na kanale YouTube. Preferuj link krótki pnedu.pl/l/{kod}. Wydarzenia live: ustaw utm_content=live-event w kampanii.',
            'color' => '#FF0000',
            'is_active' => true,
            'sort_order' => $sortOrder,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        $id = DB::table('marketing_source_types')->where('slug', 'youtube')->value('id');

        if (! $id) {
            return;
        }

        $hasCampaigns = DB::table('marketing_campaigns')->where('source_type_id', $id)->exists();

        if (! $hasCampaigns) {
            DB::table('marketing_source_types')->where('id', $id)->delete();
        }
    }
};
