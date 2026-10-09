<?php

namespace App\Models;

use App\Services\Bank\BankTransactionMatcher;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DebtCase extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_PROMISED = 'promised';

    public const STATUS_DISPUTED = 'disputed';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const SEGMENT_STANDARD = 'standard';

    public const SEGMENT_RISK = 'risk';

    public const SEGMENT_VIP = 'vip';

    public const SEGMENT_VIP_OVERDUE = 'vip_with_overdue';

    public const SEGMENT_MANUAL_REVIEW = 'manual_review';

    protected $fillable = [
        'form_order_id',
        'assigned_to_id',
        'created_by',
        'status',
        'priority',
        'customer_segment',
        'risk_score',
        'relationship_score',
        'manual_vip',
        'do_not_auto_dun',
        'vip_reason',
        'invoice_number',
        'ksef_number',
        'ifirma_invoice_id',
        'amount_gross',
        'invoice_date',
        'due_date',
        'ifirma_payment_status',
        'ifirma_synced_at',
        'opened_at',
        'next_action_at',
        'last_action_at',
        'closed_at',
        'summary',
        'closure_reason',
        'invoice_pdf_path',
        'invoice_pdf_original_name',
        'invoice_pdf_uploaded_at',
        'invoice_pdf_uploaded_by',
    ];

    protected $casts = [
        'manual_vip' => 'boolean',
        'do_not_auto_dun' => 'boolean',
        'amount_gross' => 'decimal:2',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'ifirma_synced_at' => 'datetime',
        'opened_at' => 'datetime',
        'next_action_at' => 'datetime',
        'last_action_at' => 'datetime',
        'closed_at' => 'datetime',
        'invoice_pdf_uploaded_at' => 'datetime',
    ];

    public function formOrder(): BelongsTo
    {
        return $this->belongsTo(FormOrder::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(DebtCaseAction::class)->latest('happened_at')->latest('id');
    }

    /**
     * Prawdziwe e-maile przypomnienia/ponaglenia do dłużnika (bez wysyłek testowych).
     */
    public function reminderEmailsSent(): HasMany
    {
        return $this->hasMany(DebtCaseAction::class)
            ->where('action_type', DebtCaseAction::TYPE_EMAIL)
            ->where('outcome', 'sent');
    }

    public function reminderEmailsSentCount(): int
    {
        if (array_key_exists('reminder_emails_sent_count', $this->attributes)) {
            return (int) $this->attributes['reminder_emails_sent_count'];
        }

        return (int) $this->reminderEmailsSent()->count();
    }

    /**
     * Etykieta liczby przypomnień po polsku (1 przypomnienie / 2 przypomnienia / 5 przypomnień).
     */
    public function reminderEmailsSentLabel(?int $count = null): string
    {
        $count ??= $this->reminderEmailsSentCount();
        $abs = abs($count) % 100;
        $last = $abs % 10;

        $noun = match (true) {
            $abs === 1 => 'przypomnienie',
            $last >= 2 && $last <= 4 && ($abs < 12 || $abs > 14) => 'przypomnienia',
            default => 'przypomnień',
        };

        return $count.' '.$noun;
    }

    /**
     * Kolor badge liczby przypomnień: 0 szary, 1 niebieski, 2 żółty, 3 czerwony, 4+ ciemnoczerwony.
     */
    public function reminderEmailsSentBadgeClass(?int $count = null): string
    {
        $count ??= $this->reminderEmailsSentCount();

        return match (true) {
            $count <= 0 => 'text-bg-light border text-muted',
            $count === 1 => 'text-bg-primary',
            $count === 2 => 'text-bg-warning',
            $count === 3 => 'text-bg-danger',
            default => 'text-bg-dark',
        };
    }

    /**
     * Prawdziwe SMS-y przypomnienia/ponaglenia do dłużnika (bez wysyłek testowych).
     */
    public function reminderSmsSent(): HasMany
    {
        return $this->hasMany(DebtCaseAction::class)
            ->where('action_type', DebtCaseAction::TYPE_SMS)
            ->where('outcome', 'sent');
    }

    public function reminderSmsSentCount(): int
    {
        if (array_key_exists('reminder_sms_sent_count', $this->attributes)) {
            return (int) $this->attributes['reminder_sms_sent_count'];
        }

        return (int) $this->reminderSmsSent()->count();
    }

    public function reminderSmsSentLabel(?int $count = null): string
    {
        $count ??= $this->reminderSmsSentCount();
        $abs = abs($count) % 100;
        $last = $abs % 10;

        $noun = match (true) {
            $abs === 1 => 'SMS',
            $last >= 2 && $last <= 4 && ($abs < 12 || $abs > 14) => 'SMS-y',
            default => 'SMS-ów',
        };

        return $count.' '.$noun;
    }

    public function reminderSmsSentBadgeClass(?int $count = null): string
    {
        return $this->reminderEmailsSentBadgeClass($count ?? $this->reminderSmsSentCount());
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(DebtCaseContact::class);
    }

    public function bankTransactionMatches(): HasMany
    {
        return $this->hasMany(BankTransactionMatch::class);
    }

    /**
     * Docelowa kwota FV/zamówienia dla pokrycia wpłatami z wyciągu.
     */
    public function invoiceTargetAmount(): float
    {
        $this->loadMissing('formOrder');

        return round((float) ($this->amount_gross ?? $this->formOrder?->product_price ?? 0), 2);
    }

    /**
     * Suma zaakceptowanych alokacji z wyciągu na tę sprawę.
     */
    public function acceptedBankAllocatedSum(): float
    {
        $matches = $this->relationLoaded('bankTransactionMatches')
            ? $this->bankTransactionMatches->where('status', BankTransactionMatch::STATUS_ACCEPTED)
            : $this->bankTransactionMatches()
                ->where('status', BankTransactionMatch::STATUS_ACCEPTED)
                ->with('transaction')
                ->get();

        return round((float) $matches->sum(function (BankTransactionMatch $match) {
            if ($match->allocated_amount !== null) {
                return (float) $match->allocated_amount;
            }

            return (float) ($match->transaction?->amount ?? 0);
        }), 2);
    }

    /**
     * Ile jeszcze można dopisać z wyciągu do tej FV/sprawy (0 = pełne pokrycie).
     * Gdy brak kwoty FV — null (bez limitu po stronie sprawy).
     */
    public function remainingBankAllocatableAmount(): ?float
    {
        $target = $this->invoiceTargetAmount();
        if ($target <= BankTransactionMatcher::AMOUNT_EPSILON) {
            return null;
        }

        return round(max(0, $target - $this->acceptedBankAllocatedSum()), 2);
    }

    public function isFullyCoveredByBankPayments(): bool
    {
        $remaining = $this->remainingBankAllocatableAmount();

        return $remaining !== null && $remaining <= BankTransactionMatcher::AMOUNT_EPSILON;
    }

    public function invoicePdfUploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invoice_pdf_uploaded_by');
    }

    public function hasInvoicePdf(): bool
    {
        return app(\App\Services\DebtCaseInvoicePdfService::class)->hasPdf($this);
    }

    /**
     * Soft-delete „błędnej sprawy”: tylko gdy nie ma zaakceptowanego przelewu z wyciągu.
     */
    public function canSoftDeleteAsMistake(): bool
    {
        return ! $this->bankTransactionMatches()
            ->where('status', BankTransactionMatch::STATUS_ACCEPTED)
            ->exists();
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_CLOSED]);
    }

    public function isVip(): bool
    {
        return $this->manual_vip
            || in_array($this->customer_segment, [self::SEGMENT_VIP, self::SEGMENT_VIP_OVERDUE], true);
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? (string) $this->status;
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN => 'text-bg-primary',
            self::STATUS_IN_PROGRESS => 'text-bg-info',
            self::STATUS_PROMISED => 'text-bg-warning',
            self::STATUS_DISPUTED => 'text-bg-danger',
            self::STATUS_PAUSED => 'text-bg-secondary',
            self::STATUS_CLOSED => 'text-bg-success',
            default => 'text-bg-light border',
        };
    }

    public function ifirmaPaymentStatusLabel(): string
    {
        return \App\Services\IfirmaInvoicePaymentStatusService::statusLabels()[$this->ifirma_payment_status]
            ?? (string) ($this->ifirma_payment_status ?: '—');
    }

    public function segmentLabel(): string
    {
        return self::segmentLabels()[$this->customer_segment] ?? (string) $this->customer_segment;
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_OPEN => 'Nowa',
            self::STATUS_IN_PROGRESS => 'W toku',
            self::STATUS_PROMISED => 'Obietnica płatności',
            self::STATUS_DISPUTED => 'Sporne',
            self::STATUS_PAUSED => 'Wstrzymane',
            self::STATUS_CLOSED => 'Zamknięte',
        ];
    }

    public static function segmentLabels(): array
    {
        return [
            self::SEGMENT_STANDARD => 'Standard',
            self::SEGMENT_RISK => 'Ryzyko',
            self::SEGMENT_VIP => 'VIP',
            self::SEGMENT_VIP_OVERDUE => 'VIP z zaległością',
            self::SEGMENT_MANUAL_REVIEW => 'Do weryfikacji',
        ];
    }
}
