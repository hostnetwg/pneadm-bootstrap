<?php

namespace App\Models;

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

    public function contacts(): HasMany
    {
        return $this->hasMany(DebtCaseContact::class);
    }

    public function bankTransactionMatches(): HasMany
    {
        return $this->hasMany(BankTransactionMatch::class);
    }

    public function invoicePdfUploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invoice_pdf_uploaded_by');
    }

    public function hasInvoicePdf(): bool
    {
        return app(\App\Services\DebtCaseInvoicePdfService::class)->hasPdf($this);
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
