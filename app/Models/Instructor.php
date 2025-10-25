<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivity;

class Instructor extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'instructors';

    protected $fillable = [
        'title',          // Tytuł naukowy, np. "dr", "mgr"
        'first_name',     // Imię instruktora
        'last_name',      // Nazwisko instruktora
        'gender',         // Płeć: male, female, other, prefer_not_to_say
        'email',          // Email kontaktowy
        'phone',          // Numer telefonu
        'bio',            // Krótki opis instruktora
        'bio_html',       // Pełna biografia w HTML
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

    /**
     * Zwraca polską nazwę płci.
     */
    public function getGenderLabelAttribute()
    {
        return match($this->gender) {
            'male' => 'Mężczyzna',
            'female' => 'Kobieta',
            'other' => 'Inna',
            'prefer_not_to_say' => 'Nie chcę określać',
            default => 'Nie określono'
        };
    }

    /**
     * Zwraca wszystkie dostępne opcje płci.
     */
    public static function getGenderOptions()
    {
        return [
            'male' => 'Mężczyzna',
            'female' => 'Kobieta',
            'other' => 'Inna',
            'prefer_not_to_say' => 'Nie chcę określać'
        ];
    }

    /**
     * Relacja do kursów prowadzonych przez instruktora
     */
    public function courses()
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }

    /**
     * Relacja do ankiet przypisanych do instruktora
     */
    public function surveys()
    {
        return $this->hasMany(Survey::class, 'instructor_id');
    }
}
