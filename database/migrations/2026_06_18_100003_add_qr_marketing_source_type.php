<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Typ źródła Kod QR — grafiki reklamowe szkoleń (FB, pnedu.pl itp.).
 * GA4: utm_source=qr, utm_medium=offline; wariant miejsca (fb-graphic / pnedu-graphic) ustaw w kampanii jako utm_content.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('marketing_source_types')->where('slug', 'qr')->exists()) {
            return;
        }

        $sortOrder = (int) DB::table('marketing_source_types')->max('sort_order') + 1;
        $now = now();

        DB::table('marketing_source_types')->insert([
            'name' => 'Kod QR — grafika reklamowa',
            'slug' => 'qr',
            'utm_source' => 'qr',
            'default_utm_medium' => 'offline',
            'default_utm_content' => null,
            'description' => 'Kod QR na grafikach szkoleń (Facebook, pnedu.pl, druk). Preferuj link krótki pnedu.pl/l/{kod}. Rozróżnienie miejsca publikacji: ustaw utm_content w kampanii (np. fb-graphic, pnedu-graphic).',
            'color' => '#212529',
            'is_active' => true,
            'sort_order' => $sortOrder,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        $id = DB::table('marketing_source_types')->where('slug', 'qr')->value('id');

        if (! $id) {
            return;
        }

        $hasCampaigns = DB::table('marketing_campaigns')->where('source_type_id', $id)->exists();

        if (! $hasCampaigns) {
            DB::table('marketing_source_types')->where('id', $id)->delete();
        }
    }
};
