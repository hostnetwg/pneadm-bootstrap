<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtCaseAction extends Model
{
    use HasFactory;

    public const TYPE_NOTE = 'note';

    public const TYPE_EMAIL = 'email';

    public const TYPE_SMS = 'sms';

    public const TYPE_PHONE = 'phone';

    public const TYPE_IFIRMA_REMINDER = 'ifirma_reminder';

    public const TYPE_IFIRMA_SYNC = 'ifirma_sync';

    public const TYPE_IFIRMA_PAYMENT = 'ifirma_payment';

    public const TYPE_NO_CONTACT = 'no_contact';

    public const TYPE_PAYMENT_PROMISE = 'payment_promise';

    public const TYPE_DISPUTE = 'dispute';

    public const TYPE_PAUSE = 'pause';

    public const TYPE_CLOSE = 'close';

    public const TYPE_STATUS_UPDATE = 'status_update';

    public const TYPE_CASE_OPENED = 'case_opened';

    public const TYPE_BANK_MATCH = 'bank_match';

    public const TYPE_BANK_UNMATCH = 'bank_unmatch';

    protected $fillable = [
        'debt_case_id',
        'user_id',
        'action_type',
        'channel',
        'outcome',
        'happened_at',
        'promised_payment_at',
        'next_action_at',
        'note',
    ];

    protected $casts = [
        'happened_at' => 'datetime',
        'promised_payment_at' => 'date',
        'next_action_at' => 'datetime',
    ];

    public function debtCase(): BelongsTo
    {
        return $this->belongsTo(DebtCase::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->action_type] ?? (string) $this->action_type;
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_NOTE => 'Notatka',
            self::TYPE_EMAIL => 'E-mail',
            self::TYPE_SMS => 'SMS',
            self::TYPE_PHONE => 'Telefon',
            self::TYPE_IFIRMA_REMINDER => 'iFirma',
            self::TYPE_IFIRMA_SYNC => 'Synchronizacja iFirma',
            self::TYPE_IFIRMA_PAYMENT => 'Wpłata w iFirma',
            self::TYPE_NO_CONTACT => 'Brak kontaktu',
            self::TYPE_PAYMENT_PROMISE => 'Obietnica płatności',
            self::TYPE_DISPUTE => 'Sporne',
            self::TYPE_PAUSE => 'Wstrzymanie',
            self::TYPE_CLOSE => 'Zamknięcie',
            self::TYPE_STATUS_UPDATE => 'Zmiana ustawień',
            self::TYPE_CASE_OPENED => 'Otwarcie sprawy',
            self::TYPE_BANK_MATCH => 'Wpłata z wyciągu',
            self::TYPE_BANK_UNMATCH => 'Cofnięcie przypisania przelewu',
        ];
    }
}
