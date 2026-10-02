<?php

namespace App\Services\GrowthOS\AI\Support;

/**
 * Base brand voice of PNE. An Instructor voice profile only adds style on top of these rules (DEC-035).
 */
final class PneVoice
{
    public const VERSION = 'pne_voice_v1';

    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return [
            'Rzeczowo, życzliwie i z szacunkiem dla odbiorcy (nauczyciela, dyrektora).',
            'Konkret i praktyczna korzyść zamiast ogólników i przymiotników.',
            'Bez agresywnej sprzedaży, sztucznej pilności, clickbaitu i obietnic bez pokrycia.',
            'Naturalna, poprawna polszczyzna, krótkie akapity, bez korporacyjnego żargonu.',
            'Fakty wyłącznie z przekazanych danych; niczego nie dopowiadać.',
        ];
    }
}
