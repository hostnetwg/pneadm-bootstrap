<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLog extends Model
{
    use HasFactory;

    /**
     * Nazwa tabeli
     */
    protected $table = 'activity_logs';

    /**
     * Wyłączamy updated_at (tylko created_at)
     */
    public $timestamps = false;

    /**
     * Pola możliwe do masowego przypisania
     */
    protected $fillable = [
        'user_id',
        'log_type',
        'model_type',
        'model_id',
        'model_name',
        'action',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'url',
        'method',
        'created_at',
    ];

    /**
     * Rzutowanie typów
     */
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Relacja do użytkownika
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relacja polymorphic do modelu
     */
    public function model()
    {
        return $this->morphTo('model', 'model_type', 'model_id');
    }

    /**
     * ========================================================================
     * METODY STATYCZNE DO LOGOWANIA AKCJI
     * ========================================================================
     */

    /**
     * Loguje utworzenie rekordu
     */
    public static function logCreated($model, string $description = null)
    {
        return self::logAction('create', $model, $description ?? 'Utworzono rekord', [
            'new_values' => $model->getAttributes(),
        ]);
    }

    /**
     * Loguje aktualizację rekordu
     */
    public static function logUpdated($model, string $description = null)
    {
        // Pobierz tylko zmienione wartości
        $changes = $model->getChanges();
        $original = array_intersect_key($model->getOriginal(), $changes);

        return self::logAction('update', $model, $description ?? 'Zaktualizowano rekord', [
            'old_values' => $original,
            'new_values' => $changes,
        ]);
    }

    /**
     * Loguje usunięcie rekordu (soft delete)
     */
    public static function logDeleted($model, string $description = null)
    {
        return self::logAction('delete', $model, $description ?? 'Usunięto rekord', [
            'old_values' => $model->getOriginal(),
        ]);
    }

    /**
     * Loguje przywrócenie rekordu (restore)
     */
    public static function logRestored($model, string $description = null)
    {
        return self::logAction('restore', $model, $description ?? 'Przywrócono rekord');
    }

    /**
     * Loguje wyświetlenie rekordu
     */
    public static function logViewed($model, string $description = null)
    {
        return self::logAction('view', $model, $description ?? 'Wyświetlono rekord');
    }

    /**
     * Loguje logowanie użytkownika
     */
    public static function logLogin($userId = null, string $description = 'Użytkownik zalogował się do systemu')
    {
        return self::create([
            'user_id' => $userId ?? Auth::id(),
            'log_type' => 'login',
            'action' => 'Logowanie do systemu',
            'description' => $description,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'url' => Request::fullUrl(),
            'method' => Request::method(),
            'created_at' => now(),
        ]);
    }

    /**
     * Loguje wylogowanie użytkownika
     */
    public static function logLogout($userId = null, string $description = 'Użytkownik wylogował się z systemu')
    {
        return self::create([
            'user_id' => $userId ?? Auth::id(),
            'log_type' => 'logout',
            'action' => 'Wylogowanie z systemu',
            'description' => $description,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'url' => Request::fullUrl(),
            'method' => Request::method(),
            'created_at' => now(),
        ]);
    }

    /**
     * Loguje niestandardową akcję
     */
    public static function logCustom(string $action, string $description = null, array $data = [])
    {
        return self::create(array_merge([
            'user_id' => Auth::id(),
            'log_type' => 'custom',
            'action' => $action,
            'description' => $description,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'url' => Request::fullUrl(),
            'method' => Request::method(),
            'created_at' => now(),
        ], $data));
    }

    /**
     * ========================================================================
     * METODA BAZOWA DO LOGOWANIA
     * ========================================================================
     */
    protected static function logAction(string $logType, $model, string $action, array $additionalData = [])
    {
        // Pobierz czytelną nazwę modelu
        $modelName = self::getModelName($model);

        return self::create(array_merge([
            'user_id' => Auth::id(),
            'log_type' => $logType,
            'model_type' => get_class($model),
            'model_id' => $model->id ?? null,
            'model_name' => $modelName,
            'action' => $action,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'url' => Request::fullUrl(),
            'method' => Request::method(),
            'created_at' => now(),
        ], $additionalData));
    }

    /**
     * ========================================================================
     * METODY POMOCNICZE
     * ========================================================================
     */

    /**
     * Pobiera czytelną nazwę modelu
     */
    protected static function getModelName($model): ?string
    {
        // Spróbuj pobrać nazwę z różnych pól
        $nameFields = ['title', 'name', 'nazwa', 'participant_name', 'first_name', 'product_name'];
        
        foreach ($nameFields as $field) {
            if (isset($model->$field) && !empty($model->$field)) {
                // Dla imienia i nazwiska połącz je
                if ($field === 'first_name' && isset($model->last_name)) {
                    return $model->first_name . ' ' . $model->last_name;
                }
                return $model->$field;
            }
        }
        
        // Jeśli nie znaleziono, zwróć typ modelu i ID
        $className = class_basename($model);
        return $className . ' #' . ($model->id ?? 'unknown');
    }

    /**
     * ========================================================================
     * SCOPES (ZAKRESY ZAPYTAŃ)
     * ========================================================================
     */

    /**
     * Zakres: logi dla konkretnego użytkownika
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Zakres: logi dla konkretnego typu akcji
     */
    public function scopeOfType($query, $logType)
    {
        return $query->where('log_type', $logType);
    }

    /**
     * Zakres: logi dla konkretnego modelu
     */
    public function scopeForModel($query, string $modelType, $modelId = null)
    {
        $query->where('model_type', $modelType);
        
        if ($modelId !== null) {
            $query->where('model_id', $modelId);
        }
        
        return $query;
    }

    /**
     * Zakres: logi z ostatnich X dni
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Zakres: logi z dzisiaj
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Zakres: logi z tego miesiąca
     */
    public function scopeThisMonth($query)
    {
        return $query->whereBetween('created_at', [
            now()->startOfMonth(),
            now()->endOfMonth()
        ]);
    }

    /**
     * ========================================================================
     * ACCESSORS (GETTERY)
     * ========================================================================
     */

    /**
     * Pobiera nazwę typu akcji po polsku
     */
    public function getLogTypeNameAttribute(): string
    {
        return match($this->log_type) {
            'login' => 'Logowanie',
            'logout' => 'Wylogowanie',
            'create' => 'Utworzenie',
            'update' => 'Aktualizacja',
            'delete' => 'Usunięcie',
            'view' => 'Wyświetlenie',
            'restore' => 'Przywrócenie',
            'custom' => 'Niestandardowa',
            default => 'Nieznana',
        };
    }

    /**
     * Pobiera klasę CSS dla typu akcji (dla kolorów w widoku)
     */
    public function getLogTypeColorAttribute(): string
    {
        return match($this->log_type) {
            'login' => 'success',
            'logout' => 'secondary',
            'create' => 'primary',
            'update' => 'warning',
            'delete' => 'danger',
            'view' => 'info',
            'restore' => 'success',
            'custom' => 'dark',
            default => 'secondary',
        };
    }

    /**
     * Pobiera ikonę Bootstrap Icons dla typu akcji
     */
    public function getLogTypeIconAttribute(): string
    {
        return match($this->log_type) {
            'login' => 'bi-box-arrow-in-right',
            'logout' => 'bi-box-arrow-right',
            'create' => 'bi-plus-circle',
            'update' => 'bi-pencil',
            'delete' => 'bi-trash',
            'view' => 'bi-eye',
            'restore' => 'bi-arrow-counterclockwise',
            'custom' => 'bi-gear',
            default => 'bi-circle',
        };
    }

    /**
     * Pobiera nazwę klasy modelu bez namespace
     */
    public function getModelTypeShortAttribute(): ?string
    {
        return $this->model_type ? class_basename($this->model_type) : null;
    }
}
