<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransactionMatch extends Model
{
    public const CONFIDENCE_HIGH = 'high';

    public const CONFIDENCE_MEDIUM = 'medium';

    public const CONFIDENCE_LOW = 'low';

    public const STATUS_SUGGESTED = 'suggested';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_IGNORED = 'ignored';

    public const REASON_GATEWAY_PAYOUT_PAYNOW = 'gateway_payout_paynow';

    public const REASON_MANUAL_IGNORE = 'manual_ignore';

    protected $fillable = [
        'bank_transaction_id',
        'form_order_id',
        'debt_case_id',
        'confidence',
        'match_reasons',
        'status',
        'accepted_by',
        'accepted_at',
    ];

    protected $casts = [
        'match_reasons' => 'array',
        'accepted_at' => 'datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }

    public function formOrder(): BelongsTo
    {
        return $this->belongsTo(FormOrder::class);
    }

    public function debtCase(): BelongsTo
    {
        return $this->belongsTo(DebtCase::class);
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function confidenceLabel(): string
    {
        return match ($this->confidence) {
            self::CONFIDENCE_HIGH => 'Wysoka',
            self::CONFIDENCE_MEDIUM => 'Średnia',
            self::CONFIDENCE_LOW => 'Niska',
            default => (string) $this->confidence,
        };
    }

    public function confidenceBadgeClass(): string
    {
        return match ($this->confidence) {
            self::CONFIDENCE_HIGH => 'text-bg-success',
            self::CONFIDENCE_MEDIUM => 'text-bg-warning',
            self::CONFIDENCE_LOW => 'text-bg-secondary',
            default => 'text-bg-light border',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_SUGGESTED => 'Sugestia',
            self::STATUS_ACCEPTED => 'Zaakceptowane',
            self::STATUS_REJECTED => 'Odrzucone',
            self::STATUS_IGNORED => 'Ignorowane',
            default => (string) $this->status,
        };
    }

    /**
     * Human-readable match basis for operators (Polish).
     *
     * @return list<string>
     */
    public function reasonLabels(): array
    {
        $labels = [];

        foreach ($this->match_reasons ?? [] as $reason) {
            $reason = (string) $reason;

            if (str_starts_with($reason, 'invoice_number:')) {
                $labels[] = 'Numer FV z tytułu przelewu: '.substr($reason, strlen('invoice_number:'));

                continue;
            }
            if (str_starts_with($reason, 'debt_case_invoice_number:')) {
                $labels[] = 'Numer FV z tytułu = FV na sprawie: '.substr($reason, strlen('debt_case_invoice_number:'));

                continue;
            }
            if (str_starts_with($reason, 'ksef_number:')) {
                $labels[] = 'Numer KSeF z tytułu przelewu: '.substr($reason, strlen('ksef_number:'));

                continue;
            }
            if (str_starts_with($reason, 'order_id:')) {
                $labels[] = 'ID zamówienia z tytułu przelewu (#ID / zamówienie ID): '.substr($reason, strlen('order_id:'));

                continue;
            }
            if (str_starts_with($reason, 'nip:')) {
                $labels[] = 'NIP z tytułu przelewu: '.substr($reason, strlen('nip:'));

                continue;
            }
            if (str_starts_with($reason, 'buyer_name:')) {
                $labels[] = 'Imię i nazwisko nabywcy (bez NIP) w tytule przelewu: '.substr($reason, strlen('buyer_name:'));

                continue;
            }
            if (str_starts_with($reason, 'invoice_number_in_notes:')) {
                $labels[] = 'Numer FV z tytułu przelewu występuje w notatkach zamówienia (np. anulowana FV): '.substr($reason, strlen('invoice_number_in_notes:'));

                continue;
            }
            if (str_starts_with($reason, 'ksef_mismatch:')) {
                $labels[] = 'Konflikt KSeF: w tytule przelewu jest inny numer niż na zamówieniu ('.substr($reason, strlen('ksef_mismatch:')).')';

                continue;
            }
            if (str_starts_with($reason, 'invoice_number_mismatch:')) {
                $labels[] = 'Numer FV z tytułu przelewu ('.substr($reason, strlen('invoice_number_mismatch:')).') różni się od FV na zamówieniu dopasowanym po KSeF — możliwy błąd przy wpisywaniu numeru FV';

                continue;
            }

            $labels[] = match ($reason) {
                'amount_match' => 'Kwota przelewu = kwota FV/zamówienia',
                'amount_mismatch' => 'Kwota przelewu różni się od kwoty FV/zamówienia',
                'existing_debt_case' => 'Istnieje aktywna sprawa windykacyjna dla tego zamówienia',
                'multiple_candidates' => 'Więcej niż jeden kandydat — zweryfikuj ręcznie',
                'manual_case_link' => 'Ręczne powiązanie przelewu ze sprawą windykacyjną',
                self::REASON_GATEWAY_PAYOUT_PAYNOW => 'Wypłata rozliczeniowa bramki PayNow (mElements) — poza windykacją FV',
                self::REASON_MANUAL_IGNORE => 'Ręcznie zignorowane',
                'party_name_mismatch' => 'Nadawca z wyciągu nie pasuje do nabywcy/odbiorcy zamówienia — możliwy błędny numer FV w tytule',
                default => $reason,
            };
        }

        return array_values(array_unique($labels));
    }
}
