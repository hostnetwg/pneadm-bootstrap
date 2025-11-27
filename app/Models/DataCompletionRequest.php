<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Course;

class DataCompletionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'course_id',
        'sent_at',
        'completed_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Relacja do kursu (opcjonalna)
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Sprawdza czy dla danego emaila już wysłano prośbę (która nie została uzupełniona)
     */
    public static function hasPendingRequest(string $email, ?int $courseId = null): bool
    {
        $query = self::where('email', strtolower(trim($email)))
            ->whereNull('completed_at');

        if ($courseId !== null) {
            $query->where('course_id', $courseId);
        }

        return $query->exists();
    }

    /**
     * Sprawdza czy można wysłać ponownie (token wygasł lub minęło X dni od ostatniej wysyłki)
     */
    public static function canResend(string $email, ?int $courseId = null, int $daysSinceLastSend = 30): bool
    {
        $query = self::where('email', strtolower(trim($email)))
            ->whereNull('completed_at');

        if ($courseId !== null) {
            $query->where('course_id', $courseId);
        }

        $lastRequest = $query->orderBy('sent_at', 'desc')->first();

        if (!$lastRequest) {
            return true; // Nie było wcześniejszej prośby, można wysłać
        }

        // Sprawdź czy minęło wystarczająco czasu od ostatniej wysyłki
        $daysSince = $lastRequest->sent_at->diffInDays(now());
        
        if ($daysSince >= $daysSinceLastSend) {
            return true; // Minęło wystarczająco czasu, można wysłać ponownie
        }

        // Sprawdź czy token wygasł
        $token = \App\Models\DataCompletionToken::where('email', strtolower(trim($email)))
            ->whereNull('used_at')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($token && $token->expires_at && $token->expires_at->isPast()) {
            return true; // Token wygasł, można wysłać ponownie
        }

        return false; // Nie można wysłać ponownie
    }

    /**
     * Pobiera ostatnią prośbę dla danego emaila
     */
    public static function getLastRequest(string $email, ?int $courseId = null): ?self
    {
        $query = self::where('email', strtolower(trim($email)));

        if ($courseId !== null) {
            $query->where('course_id', $courseId);
        }

        return $query->orderBy('sent_at', 'desc')->first();
    }

    /**
     * Oznacza prośbę jako uzupełnioną
     */
    public function markAsCompleted(): void
    {
        $this->update(['completed_at' => now()]);
    }
}

