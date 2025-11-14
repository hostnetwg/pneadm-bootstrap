<?php

namespace App\Traits;

use App\Models\ActivityLog;

/**
 * Trait LogsActivity
 * 
 * Automatycznie loguje akcje CRUD na modelach.
 * Dodaj ten trait do dowolnego modelu, aby włączyć automatyczne logowanie.
 * 
 * Przykład użycia:
 * ```php
 * class Course extends Model
 * {
 *     use LogsActivity;
 * }
 * ```
 */
trait LogsActivity
{
    /**
     * Boot the trait
     */
    public static function bootLogsActivity()
    {
        // Loguj utworzenie rekordu
        static::created(function ($model) {
            if (self::shouldLogActivity($model, 'created')) {
                ActivityLog::logCreated($model, self::getActionDescription($model, 'created'));
            }
        });

        // Loguj aktualizację rekordu
        static::updated(function ($model) {
            if (self::shouldLogActivity($model, 'updated')) {
                // Nie loguj jeśli nie ma żadnych zmian
                if (!empty($model->getChanges())) {
                    ActivityLog::logUpdated($model, self::getActionDescription($model, 'updated'));
                }
            }
        });

        // Loguj usunięcie rekordu
        static::deleted(function ($model) {
            if (self::shouldLogActivity($model, 'deleted')) {
                ActivityLog::logDeleted($model, self::getActionDescription($model, 'deleted'));
            }
        });

        // Loguj przywrócenie rekordu (restored)
        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model) {
                if (self::shouldLogActivity($model, 'restored')) {
                    ActivityLog::logRestored($model, self::getActionDescription($model, 'restored'));
                }
            });
        }
    }

    /**
     * Sprawdza czy należy zalogować akcję
     * 
     * Możesz nadpisać tę metodę w modelu aby dostosować warunki logowania
     */
    protected static function shouldLogActivity($model, string $event): bool
    {
        // Nie loguj jeśli użytkownik nie jest zalogowany (np. migracje, seedy)
        if (!auth()->check()) {
            return false;
        }

        // Sprawdź czy model ma właściwość $logActivityEvents
        if (property_exists($model, 'logActivityEvents')) {
            return in_array($event, $model->logActivityEvents);
        }

        // Domyślnie loguj wszystkie zdarzenia
        return true;
    }

    /**
     * Generuje opis akcji
     * 
     * Możesz nadpisać tę metodę w modelu aby dostosować opisy
     */
    protected static function getActionDescription($model, string $event): string
    {
        // Sprawdź czy model ma metodę getActivityDescription
        if (method_exists($model, 'getActivityDescription')) {
            return $model->getActivityDescription($event);
        }

        // Pobierz czytelną nazwę modelu
        $modelName = self::getReadableModelName($model);
        
        // Generuj domyślny opis
        return match($event) {
            'created' => "Utworzono: {$modelName}",
            'updated' => "Zaktualizowano: {$modelName}",
            'deleted' => "Usunięto: {$modelName}",
            'restored' => "Przywrócono: {$modelName}",
            default => "Akcja '{$event}' na: {$modelName}",
        };
    }

    /**
     * Pobiera czytelną nazwę modelu
     */
    protected static function getReadableModelName($model): string
    {
        $className = class_basename($model);
        
        // Tłumaczenie nazw modeli na polski
        $translations = [
            'Course' => 'Kurs',
            'Instructor' => 'Instruktor',
            'Participant' => 'Uczestnik',
            'FormOrder' => 'Zamówienie',
            'FormOrderParticipant' => 'Uczestnik zamówienia',
            'User' => 'Użytkownik',
            'Certificate' => 'Certyfikat',
            'Survey' => 'Ankieta',
            'CourseLocation' => 'Lokalizacja kursu',
            'CourseOnlineDetails' => 'Szczegóły kursu online',
        ];

        $translatedName = $translations[$className] ?? $className;
        
        // Dodaj nazwę rekordu jeśli dostępna
        $recordName = self::getRecordName($model);
        if ($recordName) {
            return "{$translatedName} '{$recordName}'";
        }
        
        return "{$translatedName} #{$model->id}";
    }

    /**
     * Pobiera nazwę rekordu (tytuł, nazwę itp.)
     */
    protected static function getRecordName($model): ?string
    {
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
        
        return null;
    }

    /**
     * Ręczne logowanie niestandardowej akcji
     * 
     * Przykład użycia w kontrolerze:
     * ```php
     * $course->logActivity('exported', 'Wyeksportowano listę uczestników do PDF');
     * ```
     */
    public function logActivity(string $action, string $description = null, array $data = [])
    {
        return ActivityLog::logCustom(
            $action,
            $description ?? $action,
            array_merge([
                'model_type' => get_class($this),
                'model_id' => $this->id,
                'model_name' => self::getRecordName($this),
            ], $data)
        );
    }
}










