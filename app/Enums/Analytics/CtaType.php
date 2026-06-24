<?php

namespace App\Enums\Analytics;

enum CtaType: string
{
    case ZobaczOpis = 'zobacz_opis';
    case ZapiszSie = 'zapisz_sie';
    case ZamowSzkolenie = 'zamow_szkolenie';
    case ZamowZFaktura = 'zamow_z_faktura';
    case ZarezerwujMiejsce = 'zarezerwuj_miejsce';
}
