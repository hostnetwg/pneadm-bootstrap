<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Instructor extends Model
{
    use HasFactory;

    protected $table = 'instructors';

    protected $fillable = [
        'title',          // Tytuł naukowy, np. "dr", "mgr"
        'first_name',     // Imię instruktora
        'last_name',      // Nazwisko instruktora
        'email',          // Email kontaktowy
        'phone',          // Numer telefonu
        'bio',            // Krótki opis instruktora
        'photo',          // Ścieżka do zdjęcia
        'signature',      // Ścieżka do podpisu instruktora
        'is_active',      // Czy instruktor jest aktywny
    ];

    /**
     * Zwraca pełne imię i nazwisko.
     */
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Zwraca tytuł wraz z pełnym imieniem i nazwiskiem.
     * Przykład: "dr Jan Kowalski" lub "mgr Anna Nowak".
     */
    public function getFullTitleNameAttribute()
    {
        return trim("{$this->title} {$this->first_name} {$this->last_name}");
    }
}
