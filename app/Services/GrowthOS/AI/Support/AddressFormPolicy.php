<?php

namespace App\Services\GrowthOS\AI\Support;

/**
 * Project-level address form for Growth OS AI drafts (ty | panstwo).
 * Voice profiles never override this; a clear user instruction may override for one operation only.
 */
final class AddressFormPolicy
{
    public const TY = 'ty';

    public const PANSTWO = 'panstwo';

    public const DEFAULT = self::TY;

    public const CHANNEL_WRITTEN = 'written';

    public const CHANNEL_LIVE = 'live';

    public const CHANNEL_PLANNING = 'planning';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [self::TY, self::PANSTWO];
    }

    public static function normalize(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';

        return in_array($value, self::values(), true) ? $value : self::DEFAULT;
    }

    /**
     * @param  array<string, mixed>  $project
     */
    public static function fromProject(array $project): string
    {
        return self::normalize($project['address_form'] ?? null);
    }

    /**
     * Effective form for one AI call. Override only when the instruction clearly asks to change address form.
     *
     * @return array{form: string, overridden: bool}
     */
    public static function resolve(string $projectForm, string $instruction = '', ?string $previousEffective = null): array
    {
        $projectForm = self::normalize($projectForm);
        $fromInstruction = self::detectOverride($instruction);
        if ($fromInstruction !== null) {
            return ['form' => $fromInstruction, 'overridden' => $fromInstruction !== $projectForm];
        }

        if ($previousEffective !== null) {
            $previous = self::normalize($previousEffective);
            if ($previous !== $projectForm && trim($instruction) !== '') {
                // Iterate: keep a conscious previous override unless the instruction revokes it.
                return ['form' => $previous, 'overridden' => true];
            }
        }

        return ['form' => $projectForm, 'overridden' => false];
    }

    public static function detectOverride(string $instruction): ?string
    {
        $text = mb_strtolower(trim($instruction));
        if ($text === '') {
            return null;
        }

        $panstwo = [
            'na państwo',
            'formie państwo',
            'formę państwo',
            'forma państwo',
            'per państwo',
            'zwracaj się państwo',
            'zwracaj sie panstwo',
            'pisz państwo',
            'pisz formalnie',
            'formalnie, w formie państwo',
            'formalnie w formie państwo',
            'w formie państwo',
            'na panstwo',
            'formie panstwo',
            'forme panstwo',
            'forma panstwo',
            'per panstwo',
            'pisz panstwo',
            'w formie panstwo',
        ];
        foreach ($panstwo as $needle) {
            if (str_contains($text, $needle)) {
                return self::PANSTWO;
            }
        }

        $ty = [
            'na ty',
            'per ty',
            'formie ty',
            'formę ty',
            'forma ty',
            'zwracaj się na ty',
            'zwracaj sie na ty',
            'pisz na ty',
            'pisz bezpośrednio',
            'pisz bezposrednio',
            'bezpośrednio na ty',
            'bezposrednio na ty',
            'w formie ty',
        ];
        foreach ($ty as $needle) {
            if (str_contains($text, $needle)) {
                return self::TY;
            }
        }

        return null;
    }

    /**
     * Shared prompt section injected into AI tasks.
     */
    public static function promptSection(string $form, string $channel = self::CHANNEL_WRITTEN): string
    {
        $form = self::normalize($form);
        $presenter = <<<'TEXT'
PROWADZĄCY VS ORGANIZACJA:
Jeżeli webinar prowadzi jedna osoba, jej działania opisuj w 1. os. lp.: „pokażę”, „omówię”, „zademonstruję”, „opowiem”.
Nie pisz automatycznie „pokażemy” / „omówimy”, gdy jest jeden prowadzący.
„My” tylko dla realnych działań PNE / zespołu (np. udostępnimy nagranie, prześlemy informacje, przygotowaliśmy materiały).
TEXT;

        if ($form === self::PANSTWO) {
            $address = match ($channel) {
                self::CHANNEL_LIVE => <<<'TEXT'
FORMA ZWROTU (address_form=panstwo):
Do widzów zwracaj się konsekwentnie „Państwo”: „zobaczą Państwo”, „mogą Państwo”, „pokażę Państwu”, „jeżeli Państwo chcą”.
Nie mieszaj z formą „Ty” ani „Wy”.
Ton profesjonalny i naturalny — nie bardziej urzędowy niż trzeba.
TEXT,
                self::CHANNEL_PLANNING => <<<'TEXT'
FORMA ZWROTU (address_form=panstwo):
Teksty skierowane do odbiorcy webinaru zapisuj w formie „Państwo” (otrzymają Państwo, zobaczą Państwo).
Nie mieszaj z „Ty”. Ton profesjonalny, naturalny.
TEXT,
                default => <<<'TEXT'
FORMA ZWROTU (address_form=panstwo):
Zwracaj się do odbiorcy konsekwentnie „Państwo”: „otrzymają Państwo”, „zobaczą Państwo”, „mogą Państwo”, „zapraszam Państwa”.
Nie mieszaj z formą „Ty”.
Ton profesjonalny i naturalny — nie bardziej urzędowy niż trzeba.
TEXT,
            };
        } else {
            $address = match ($channel) {
                self::CHANNEL_LIVE => <<<'TEXT'
FORMA ZWROTU (address_form=ty):
Do grupy na żywo używaj naturalnego „Wy/Wam”: „pokażę Wam”, „zobaczycie”, „spróbujcie”, „jeśli chcecie”, „zwróćcie uwagę”.
Nie wymuszaj przez całe wystąpienie sztucznego „pokażę Ci” / „zobaczysz”.
Nie używaj „Państwo”.
Ton bezpośredni, profesjonalny, bez poufałości, slangu i młodzieżowego języka.
TEXT,
                self::CHANNEL_PLANNING => <<<'TEXT'
FORMA ZWROTU (address_form=ty):
Teksty skierowane do odbiorcy webinaru zapisuj bezpośrednio na „Ty” (otrzymasz, zobaczysz, możesz).
Nie używaj „Państwo”. Ton bezpośredni, profesjonalny, bez poufałości i slangu.
TEXT,
                default => <<<'TEXT'
FORMA ZWROTU (address_form=ty):
Zwracaj się do odbiorcy bezpośrednio na „Ty”: „otrzymasz”, „zobaczysz”, „sprawdzisz”, „możesz”, „zapraszam”.
Nie używaj „otrzymają Państwo”, „zobaczą Państwo”, „mogą Państwo”.
Nie mieszaj „Ty” i „Państwo”.
Ton bezpośredni, naturalny, profesjonalny — bez poufałości, slangu, „hejka”, agresywnej sprzedaży i sztucznego młodzieżowego języka. „Ty” nie obniża eksperckiego tonu.
TEXT,
            };
        }

        $hierarchy = <<<'TEXT'
HIERARCHIA (formy zwrotu):
1) bezpieczeństwo i fakty projektu mają pierwszeństwo,
2) jawna bieżąca instrukcja użytkownika może nadpisać formę zwrotu TYLKO gdy wyraźnie o nią prosi (np. „napisz na Państwo”, „pisz na Ty”),
3) w przeciwnym razie obowiązuje campaign.address_form / style.address_form z wejścia,
4) profil głosu (rytm, słownictwo) NIE zmienia formy zwrotu,
5) frazy typu „napisz krócej” albo „bardziej profesjonalnie” NIE zmieniają formy zwrotu na Państwo/Ty.
TEXT;

        return trim($address."\n\n".$presenter."\n\n".$hierarchy);
    }

    /**
     * Shared prompt block for tasks whose instructions() cannot see the request context.
     * The model must follow style.address_form from the JSON input.
     */
    public static function promptBlock(string $channel = self::CHANNEL_WRITTEN): string
    {
        $ty = self::promptSection(self::TY, $channel);
        $panstwo = self::promptSection(self::PANSTWO, $channel);

        return <<<PROMPT
POLITYKA FORMY ZWROTU (Address Form Policy):
Wejście JSON zawiera style.address_form = "ty" albo "panstwo" (efektywna forma tej operacji).
Stosuj WYŁĄCZNIE reguły dla tej wartości. Nie mieszaj form w jednym tekście.
Jeżeli user_instruction / instruction jawnie prosi o drugą formę, style.address_form już to odzwierciedla — trzymaj się stylu.address_form.

=== gdy style.address_form = "ty" ===
{$ty}

=== gdy style.address_form = "panstwo" ===
{$panstwo}
PROMPT;
    }

    public static function label(string $form): string
    {
        return self::normalize($form) === self::PANSTWO ? 'Państwo' : 'Ty';
    }
}
