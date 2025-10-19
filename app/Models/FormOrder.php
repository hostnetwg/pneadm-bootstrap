<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model FormOrder
 * 
 * Odpowiednik tabeli zamowienia_FORM z bazy certgen
 * Używa bazy pneadm (głównej bazy aplikacji)
 * 
 * Przechowuje zamówienia złożone przez formularz na stronie
 */
class FormOrder extends Model
{
    use HasFactory;

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
        
        // Dane uczestnika
        'participant_name',
        'participant_email',
        
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
     * 
     * UWAGA: order_date NIE jest rzutowane na datetime, aby uniknąć automatycznej
     * konwersji strefy czasowej. Data w bazie jest już w polskiej strefie czasowej.
     */
    protected $casts = [
        'ptw' => 'integer',
        // 'order_date' => 'datetime', // Celowo zakomentowane - data już jest w polskiej strefie
        'product_id' => 'integer',
        'product_price' => 'decimal:2',
        'publigo_product_id' => 'integer',
        'publigo_price_id' => 'integer',
        'publigo_sent' => 'integer',
        'publigo_sent_at' => 'datetime',
        'invoice_payment_delay' => 'integer',
        'status_completed' => 'integer',
        'updated_manually_at' => 'datetime',
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
     * Scope - tylko nowe zamówienia (bez faktury i niezakończone)
     */
    public function scopeNew($query)
    {
        return $query->where(function($q) {
            $q->whereNull('invoice_number')
              ->orWhere('invoice_number', '')
              ->orWhere('invoice_number', '0');
        })->where(function($q) {
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
        return !empty($this->invoice_number) 
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
            trim(($this->orderer_postal_code ?? '') . ' ' . ($this->orderer_city ?? '')),
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
            trim(($this->buyer_postal_code ?? '') . ' ' . ($this->buyer_city ?? '')),
        ]);
        
        if (!empty($this->buyer_nip)) {
            $parts[] = 'NIP: ' . preg_replace('/[^0-9]/', '', $this->buyer_nip);
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
            trim(($this->recipient_postal_code ?? '') . ' ' . ($this->recipient_city ?? '')),
        ]);
        
        if (!empty($this->recipient_nip)) {
            $parts[] = 'NIP: ' . preg_replace('/[^0-9]/', '', $this->recipient_nip);
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
                    ->where('is_primary', 1);
    }

    /**
     * Accessor - liczba uczestników (z nowej tabeli)
     */
    public function getParticipantsCountAttribute(): int
    {
        return $this->participants()->count();
    }
}
