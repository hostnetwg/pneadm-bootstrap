<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalizacja utm_source / default_utm_medium wg docs/MARKETING.md (audyt 2026-06).
 * Nie zmienia slugów ani nazw — tylko wartości trafiające do generatora linków.
 */
return new class extends Migration
{
    public function up(): void
    {
        $updates = [
            // Email z legacy slug „900” — medium było błędnie „paid”
            2 => ['utm_source' => 'newsletter', 'default_utm_medium' => 'email'],
            // Training: utm_source „webinar” myli GA (source = platforma, nie format)
            5 => ['utm_source' => 'pnedu', 'default_utm_medium' => 'webinar'],
            8 => ['utm_source' => 'tiktok', 'default_utm_medium' => 'paid'],
            9 => ['utm_source' => 'newsletter', 'default_utm_medium' => 'email'],
            10 => ['utm_source' => 'newsletter', 'default_utm_medium' => 'email'],
            // Duplikat Website — 0 kampanii; wyłącz z listy nowych kampanii
            11 => [
                'utm_source' => 'pnedu',
                'default_utm_medium' => 'referral',
                'is_active' => false,
            ],
        ];

        foreach ($updates as $id => $values) {
            DB::table('marketing_source_types')
                ->where('id', $id)
                ->update(array_merge($values, ['updated_at' => now()]));
        }
    }

    public function down(): void
    {
        $rollback = [
            2 => ['utm_source' => null, 'default_utm_medium' => 'paid', 'is_active' => true],
            5 => ['utm_source' => 'webinar', 'default_utm_medium' => 'webinar', 'is_active' => true],
            8 => ['utm_source' => null, 'default_utm_medium' => 'paid', 'is_active' => true],
            9 => ['utm_source' => null, 'default_utm_medium' => 'paid', 'is_active' => true],
            10 => ['utm_source' => null, 'default_utm_medium' => 'paid', 'is_active' => true],
            11 => ['utm_source' => null, 'default_utm_medium' => 'paid', 'is_active' => true],
        ];

        foreach ($rollback as $id => $values) {
            DB::table('marketing_source_types')
                ->where('id', $id)
                ->update(array_merge($values, ['updated_at' => now()]));
        }
    }
};
