<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Model FormOrder
 *
 * Odpowiednik tabeli zamowienia_FORM z bazy certgen
 * Używa bazy pneadm (głównej bazy aplikacji)
 *
 * Przechowuje zamówienia złożone przez formularz na stronie
 *
 * @property \Carbon\Carbon|null $pnedu_provisioned_at Kolumna z komentarzem w DB: data przyznania dostępu PNEDU; null = akcja nie wykonana.
 * @property bool|null $pnedu_user_existed_before Kolumna z komentarzem w DB: czy konto w pnedu.users istniało przed akcją (null przed pierwszym użyciem).
 */
class FormOrder extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public const PAYMENT_MODE_DEFERRED_INVOICE = 'deferred_invoice';

    public const PAYMENT_MODE_ONLINE_GATEWAY = 'online_gateway';

    public const PAYMENT_STATUS_SUBMITTED = 'submitted';

    public const PAYMENT_STATUS_AWAITING_PAYMENT = 'awaiting_payment';

    public const PAYMENT_STATUS_PAID = 'paid';

    public const PAYMENT_STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_STATUS_FAILED = 'failed';

    /**
     * Nazwa tabeli
     */
    protected $table = 'form_orders';

    /**
     * Połączenie z bazą danych - główna baza pneadm (mysql)
     */
    protected $connection = 'mysql';

    /**
     * Czy model używa timestampów (created_at, updated_at)
     */
    public $timestamps = true;

    /**
     * Pola możliwe do masowego przypisania
     */
    protected $fillable = [
        // ID - umożliwiamy ustawianie podczas migracji
        'id',

        // Identyfikatory
        'ident',
        'ptw',

        // Dane zamówienia
        'order_date',

        // Produkt/szkolenie
        'product_id',
        'product_name',
        'product_price',
        'product_description',

        // Integracja z Publigo
        'publigo_product_id',
        'publigo_price_id',
        'publigo_sent',
        'publigo_sent_at',
        'pnedu_provisioned_at',
        'pnedu_user_existed_before',

        // Dane zamawiającego (kontaktowe)
        'orderer_name',
        'orderer_address',
        'orderer_postal_code',
        'orderer_city',
        'orderer_phone',
        'orderer_email',

        // Dane nabywcy (do faktury)
        'buyer_name',
        'buyer_address',
        'buyer_postal_code',
        'buyer_city',
        'buyer_nip',

        // Dane odbiorcy
        'recipient_name',
        'recipient_address',
        'recipient_postal_code',
        'recipient_city',
        'recipient_nip',

        // Dane do faktury
        'invoice_number',
        'invoice_notes',
        'invoice_payment_delay',
        'payment_mode',
        'payment_status',

        // Dane KSeF
        'ksef_number',
        'ksef_sent_at',
        'ksef_status',
        'ksef_error',

        // Status i notatki
        'status_completed',
        'notes',
        'updated_manually_at',

        // Dane techniczne
        'ip_address',
        'fb_source',
    ];

    /**
     * Rzutowanie typów dla atrybutów
     */
    protected $casts = [
        'ptw' => 'integer',
        'product_id' => 'integer',
        'product_price' => 'decimal:2',
        'publigo_product_id' => 'integer',
        'publigo_price_id' => 'integer',
        'publigo_sent' => 'integer',
        'order_date' => 'datetime',
        'publigo_sent_at' => 'datetime',
        'pnedu_provisioned_at' => 'datetime',
        'pnedu_user_existed_before' => 'boolean',
        'invoice_payment_delay' => 'integer',
        'status_completed' => 'integer',
        'updated_manually_at' => 'datetime',
        'ksef_sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Atrybuty ukryte przy serializacji
     */
    protected $hidden = [];

    /**
     * Domyślne wartości atrybutów
     */
    protected $attributes = [
        'publigo_sent' => 0,
        'status_completed' => 0,
    ];

    /**
     * Zwraca surową wartość order_date z bazy (bez żadnej konwersji)
     * Użyj tego jeśli potrzebujesz dokładnie tego co jest w bazie
     */
    public function getOrderDateRawAttribute()
    {
        return $this->attributes['order_date'] ?? null;
    }

    /**
     * Mutator - zapisuje datę w UTC w formacie zgodnym z bazą
     * Zawsze zapisujemy daty w UTC, niezależnie od timezone aplikacji
     */
    public function setOrderDateAttribute($value): void
    {
        if ($value instanceof \DateTimeInterface) {
            // Konwertuj na UTC przed zapisem
            $carbon = Carbon::instance($value)->utc();
            $this->attributes['order_date'] = $carbon->format('Y-m-d H:i:s');

            return;
        }

        if (is_numeric($value)) {
            // Timestamp zawsze jest w UTC
            $this->attributes['order_date'] = Carbon::createFromTimestamp($value, 'UTC')->format('Y-m-d H:i:s');

            return;
        }

        // Parsuj i konwertuj na UTC
        // Carbon::parse() używa timezone aplikacji, więc musimy przekonwertować na UTC
        $carbon = Carbon::parse($value);
        $this->attributes['order_date'] = $carbon->utc()->format('Y-m-d H:i:s');
    }

    /**
     * Scope - tylko nowe zamówienia (bez faktury i niezakończone)
     */
    public function scopeNew($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('invoice_number')
                ->orWhere('invoice_number', '')
                ->orWhere('invoice_number', '0');
        })->where(function ($q) {
            $q->where('status_completed', '!=', 1)
                ->orWhereNull('status_completed');
        });
    }

    /**
     * Scope - zamówienia zakończone
     */
    public function scopeCompleted($query)
    {
        return $query->where('status_completed', 1);
    }

    /**
     * Scope - zamówienia z fakturą
     */
    public function scopeWithInvoice($query)
    {
        return $query->whereNotNull('invoice_number')
            ->where('invoice_number', '!=', '')
            ->where('invoice_number', '!=', '0');
    }

    /**
     * Scope - zamówienia wysłane do Publigo
     */
    public function scopeSentToPubligo($query)
    {
        return $query->where('publigo_sent', 1);
    }

    /**
     * Scope - zamówienia niewysłane do Publigo (ale mające dane Publigo)
     */
    public function scopeNotSentToPubligo($query)
    {
        return $query->where('publigo_sent', 0)
            ->whereNotNull('publigo_product_id')
            ->whereNotNull('publigo_price_id');
    }

    /**
     * Scope - wykrywanie duplikatów (ten sam email głównego uczestnika + to samo szkolenie)
     * Źródło e-maila: form_order_participants (is_primary), nie kolumny form_orders.
     */
    public function scopeDuplicates($query)
    {
        $table = $query->getModel()->getTable();

        return $query
            ->join('form_order_participants as fop', function ($join) use ($table) {
                $join->on($table.'.id', '=', 'fop.form_order_id')
                    ->where('fop.is_primary', '=', 1)
                    ->whereNull('fop.deleted_at');
            })
            ->selectRaw('LOWER(TRIM(fop.participant_email)) as participant_email')
            ->addSelect($table.'.publigo_product_id')
            ->selectRaw('COUNT(*) as duplicate_count')
            ->selectRaw('GROUP_CONCAT('.$table.'.id ORDER BY '.$table.'.id) as order_ids')
            ->whereNotNull('fop.participant_email')
            ->where('fop.participant_email', '!=', '')
            ->whereNotNull($table.'.publigo_product_id')
            ->groupByRaw('LOWER(TRIM(fop.participant_email)), '.$table.'.publigo_product_id')
            ->having('duplicate_count', '>', 1);
    }

    /**
     * Zamówienia, których główny uczestnik ma podany e-mail (porównanie bez uwagi na wielkość liter).
     */
    public function scopeWherePrimaryParticipantEmailMatches(Builder $query, string $email): Builder
    {
        $ordersTable = $query->getModel()->getTable();
        $normalized = strtolower(trim($email));

        return $query->whereExists(function ($q) use ($normalized, $ordersTable) {
            $q->select(DB::raw('1'))
                ->from('form_order_participants as fop')
                ->whereColumn('fop.form_order_id', $ordersTable.'.id')
                ->where('fop.is_primary', 1)
                ->whereNull('fop.deleted_at')
                ->whereRaw('LOWER(TRIM(fop.participant_email)) = ?', [$normalized]);
        });
    }

    /**
     * Scope - pobierz zamówienia które są duplikatami (mają duplikaty)
     */
    public function scopeWithDuplicates($query)
    {
        $duplicateGroups = self::duplicates()->get();
        $orderIds = [];

        foreach ($duplicateGroups as $group) {
            $ids = explode(',', $group->order_ids);
            $orderIds = array_merge($orderIds, $ids);
        }

        return $query->whereIn('id', $orderIds);
    }

    /**
     * Scope - znajdź duplikaty dla konkretnego zamówienia
     */
    public function scopeFindDuplicatesFor($query, $orderId)
    {
        $order = self::with('primaryParticipant')->find($orderId);
        $email = $order?->display_participant_email;
        if (! $order || empty(trim((string) $email)) || ! $order->publigo_product_id) {
            return $query->where('id', -1); // Pusty wynik
        }

        return $query
            ->where('publigo_product_id', $order->publigo_product_id)
            ->where('id', '!=', $orderId)
            ->wherePrimaryParticipantEmailMatches($email);
    }

    /**
     * Accessor - czy zamówienie jest nowe (bez faktury i niezakończone)
     */
    public function getIsNewAttribute(): bool
    {
        return (empty($this->invoice_number) || $this->invoice_number == '0')
               && $this->status_completed != 1;
    }

    /**
     * Accessor - czy zamówienie ma wystawioną fakturę
     */
    public function getHasInvoiceAttribute(): bool
    {
        return ! empty($this->invoice_number)
               && $this->invoice_number != '0';
    }

    /**
     * Accessor - czy zamówienie zostało wysłane do Publigo
     */
    public function getIsSentToPubligoAttribute(): bool
    {
        return $this->publigo_sent == 1;
    }

    /**
     * Accessor - sformatowany NIP (tylko cyfry)
     */
    public function getFormattedNipAttribute(): ?string
    {
        if (empty($this->buyer_nip)) {
            return null;
        }

        return preg_replace('/[^0-9]/', '', $this->buyer_nip);
    }

    /**
     * Accessor - pełny adres zamawiającego
     */
    public function getOrdererFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->orderer_name,
            $this->orderer_address,
            trim(($this->orderer_postal_code ?? '').' '.($this->orderer_city ?? '')),
        ]);

        return implode("\n", $parts);
    }

    /**
     * Accessor - pełny adres nabywcy
     */
    public function getBuyerFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->buyer_name,
            $this->buyer_address,
            trim(($this->buyer_postal_code ?? '').' '.($this->buyer_city ?? '')),
        ]);

        if (! empty($this->buyer_nip)) {
            $parts[] = 'NIP: '.preg_replace('/[^0-9]/', '', $this->buyer_nip);
        }

        return implode("\n", $parts);
    }

    /**
     * Accessor - pełny adres odbiorcy
     */
    public function getRecipientFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->recipient_name,
            $this->recipient_address,
            trim(($this->recipient_postal_code ?? '').' '.($this->recipient_city ?? '')),
        ]);

        if (! empty($this->recipient_nip)) {
            $parts[] = 'NIP: '.preg_replace('/[^0-9]/', '', $this->recipient_nip);
        }

        return implode("\n", $parts);
    }

    /**
     * Accessor - sformatowany NIP odbiorcy (tylko cyfry)
     */
    public function getRecipientFormattedNipAttribute(): ?string
    {
        if (empty($this->recipient_nip)) {
            return null;
        }

        return preg_replace('/[^0-9]/', '', $this->recipient_nip);
    }

    /**
     * Relacja do uczestników (nowa tabela form_order_participants)
     */
    public function participants()
    {
        return $this->hasMany(FormOrderParticipant::class, 'form_order_id');
    }

    /**
     * Accessor - główny uczestnik (z nowej tabeli)
     */
    public function primaryParticipant()
    {
        return $this->hasOne(FormOrderParticipant::class, 'form_order_id')
            ->where('is_primary', 1)
            ->whereNull('deleted_at');
    }

    /**
     * Nazwa uczestnika do wyświetlenia (form_order_participants – główny uczestnik).
     */
    public function getDisplayParticipantNameAttribute(): string
    {
        $p = $this->primaryParticipant;
        if ($p && trim(($p->participant_firstname ?? '').' '.($p->participant_lastname ?? '')) !== '') {
            return trim($p->participant_firstname.' '.$p->participant_lastname);
        }

        return '';
    }

    /**
     * E-mail uczestnika do wyświetlenia (form_order_participants – główny uczestnik).
     */
    public function getDisplayParticipantEmailAttribute(): ?string
    {
        $p = $this->primaryParticipant;
        if ($p && ! empty(trim((string) ($p->participant_email ?? '')))) {
            return trim((string) $p->participant_email);
        }

        return null;
    }

    /**
     * Accessor - liczba uczestników (z nowej tabeli)
     */
    public function getParticipantsCountAttribute(): int
    {
        return $this->participants()->count();
    }

    /**
     * Płatność online przez PayU ze statusem „opłacone” – najwyższy priorytet przy wyborze zamówienia w grupie duplikatów.
     */
    public function isPayuPaidOnlineOrder(): bool
    {
        if ($this->payment_mode !== self::PAYMENT_MODE_ONLINE_GATEWAY) {
            return false;
        }
        if ($this->payment_status !== self::PAYMENT_STATUS_PAID) {
            return false;
        }
        $gatewayCode = null;
        if ($this->relationLoaded('onlinePaymentOrders')) {
            $gatewayCode = $this->onlinePaymentOrders->sortByDesc('id')->first()?->payment_gateway;
        } else {
            $gatewayCode = $this->onlinePaymentOrders()->orderByDesc('id')->value('payment_gateway');
        }

        return strtolower((string) $gatewayCode) === 'payu';
    }

    /**
     * Accessor - priorytet zamówienia (im wyższy, tym ważniejsze)
     * Używane do określenia które zamówienie zachować w grupie duplikatów
     */
    public function getPriorityAttribute(): int
    {
        $priority = 0;

        // Najwyższy priorytet w duplikatach: PayU + opłacone (powyżej dotychczasowej hierarchii, w tym „ma fakturę”)
        if ($this->isPayuPaidOnlineOrder()) {
            $priority += 25000000;
        }

        // NAJWYŻSZY PRIORYTET - ma fakturę (zawsze zachowaj)
        if ($this->has_invoice) {
            $priority += 10000000;
        }

        // WYSOKI PRIORYTET - nie jest zakończone (aktywne) - wyższy niż zakończone bez faktury
        if (! $this->is_completed) {
            $priority += 5000000;
        }

        // NISKI PRIORYTET - jest zakończone (ale bez faktury) - najniższy priorytet
        if ($this->is_completed) {
            $priority += 1000000;
        }

        // BARDZO NISKI PRIORYTET - ma notatkę "duplikat"
        if (stripos($this->notes ?? '', 'duplikat') !== false) {
            $priority -= 10000000;
        }

        // PRIORYTET CHRONOLOGICZNY - zależy od statusu przetworzenia
        if ($this->has_invoice) {
            // Dla zamówień z fakturą - starsze mają wyższy priorytet
            $priority += (1000000 - $this->id);
        } elseif ($this->is_completed) {
            // Dla zakończonych zamówień - starsze mają wyższy priorytet (ale niski)
            $priority += (100000 - $this->id);
        } else {
            // Dla aktywnych zamówień - NOWSZE mają wyższy priorytet
            // (klient mógł poprawić dane w kolejnym formularzu)
            $priority += $this->id; // Im wyższe ID, tym wyższy priorytet
        }

        return $priority;
    }

    /**
     * Accessor - czy zamówienie jest zakończone
     */
    public function getIsCompletedAttribute(): bool
    {
        return $this->status_completed == 1;
    }

    /**
     * Accessor - czy zamówienie jest oznaczone jako duplikat w notatkach
     */
    public function getIsMarkedAsDuplicateAttribute(): bool
    {
        return stripos($this->notes ?? '', 'duplikat') !== false;
    }

    /**
     * Accessor - opis powodu priorytetu (dla wyświetlania w UI)
     */
    public function getPriorityReasonAttribute(): string
    {
        $reasons = [];

        if ($this->isPayuPaidOnlineOrder()) {
            $reasons[] = '🏆 Płatność online (PayU) – opłacone (najwyższy priorytet w duplikatach)';
        }

        if ($this->has_invoice) {
            $reasons[] = '✅ Ma fakturę (najwyższy priorytet)';
        } elseif (! $this->is_completed) {
            $reasons[] = '✅ Aktywne zamówienie (wymaga przetworzenia)';
        } elseif ($this->is_marked_as_duplicate) {
            $reasons[] = '❌ Oznaczone jako duplikat';
        } else {
            $reasons[] = '⚠️ Zakończone bez faktury (najniższy priorytet)';
        }

        // Dodaj informację o wieku zamówienia i logice chronologicznej
        if ($this->created_at) {
            $daysOld = $this->created_at->diffInDays(now());
            if ($daysOld == 0) {
                $reasons[] = '🕐 Zamówienie z dzisiaj';
            } elseif ($daysOld == 1) {
                $reasons[] = '🕐 Zamówienie z wczoraj';
            } elseif ($daysOld < 7) {
                $reasons[] = "🕐 Zamówienie sprzed {$daysOld} dni";
            } else {
                $reasons[] = "🕐 Zamówienie sprzed {$daysOld} dni";
            }
        }

        // Dodaj informację o logice chronologicznej
        if ($this->has_invoice) {
            $reasons[] = '📅 Starsze zamówienie (z fakturą)';
        } elseif ($this->is_completed) {
            $reasons[] = '📅 Starsze zamówienie (zakończone)';
        } else {
            $reasons[] = '🆕 Najnowsze zamówienie (może mieć poprawione dane)';
        }

        return implode("\n", $reasons);
    }

    /**
     * Relacja do kampanii marketingowej
     */
    public function marketingCampaign()
    {
        return $this->belongsTo(MarketingCampaign::class, 'fb_source', 'campaign_code');
    }

    /**
     * Szkolenie z tabeli courses (form_orders.product_id → courses.id).
     */
    public function course()
    {
        return $this->belongsTo(Course::class, 'product_id');
    }

    /**
     * Rekordy płatności online (PayU / Paynow) powiązane z tym zamówieniem z formularza.
     */
    public function onlinePaymentOrders()
    {
        return $this->hasMany(OnlinePaymentOrder::class, 'form_order_id');
    }

    /**
     * Etykieta trybu rozliczenia; dla online z nazwą bramki z online_payment_orders.
     */
    public function paymentModeLabelWithGateway(): string
    {
        $gatewayCode = null;
        if ($this->payment_mode === self::PAYMENT_MODE_ONLINE_GATEWAY) {
            $opo = $this->relationLoaded('onlinePaymentOrders')
                ? $this->onlinePaymentOrders->sortByDesc('id')->first()
                : $this->onlinePaymentOrders()->orderByDesc('id')->first();
            $gatewayCode = $opo?->payment_gateway;
        }

        return self::paymentModeLabel($this->payment_mode, $gatewayCode);
    }

    public static function paymentModeLabel(?string $mode, ?string $onlinePaymentGateway = null): string
    {
        return match ($mode) {
            self::PAYMENT_MODE_ONLINE_GATEWAY => match (strtolower((string) $onlinePaymentGateway)) {
                'payu' => 'Płatność online (bramka: PayU)',
                'paynow' => 'Płatność online (bramka: Paynow)',
                default => 'Płatność online (bramka)',
            },
            self::PAYMENT_MODE_DEFERRED_INVOICE => 'Faktura z odroczonym terminem',
            default => $mode ? (string) $mode : '—',
        };
    }

    public static function paymentStatusLabel(?string $status): string
    {
        return match ($status) {
            self::PAYMENT_STATUS_SUBMITTED => 'Złożone (odroczona)',
            self::PAYMENT_STATUS_AWAITING_PAYMENT => 'Oczekuje na płatność',
            self::PAYMENT_STATUS_PAID => 'Opłacone',
            self::PAYMENT_STATUS_CANCELLED => 'Anulowane',
            self::PAYMENT_STATUS_FAILED => 'Błąd płatności',
            default => $status ? (string) $status : '—',
        };
    }

    public function paymentModeBadgeClass(): string
    {
        return match ($this->payment_mode) {
            self::PAYMENT_MODE_ONLINE_GATEWAY => 'info',
            self::PAYMENT_MODE_DEFERRED_INVOICE => 'secondary',
            default => 'light text-dark',
        };
    }

    public function paymentStatusBadgeClass(): string
    {
        return match ($this->payment_status) {
            self::PAYMENT_STATUS_PAID => 'success',
            self::PAYMENT_STATUS_AWAITING_PAYMENT => 'warning text-dark',
            self::PAYMENT_STATUS_CANCELLED, self::PAYMENT_STATUS_FAILED => 'danger',
            self::PAYMENT_STATUS_SUBMITTED => 'primary',
            default => 'light text-dark',
        };
    }
}
