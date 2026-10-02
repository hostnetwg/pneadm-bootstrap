<?php

namespace App\Models;

use App\Models\GrowthOS\GrowthCampaign;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Instructor extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

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
        'default_settlement_type',
        'notes',          // Notatki na temat trenera
        'ai_voice_profile', // Profil komunikacji dla AI (DEC-035), edytuje tylko super_admin
        'website_url',    // URL strony WWW
        'linkedin_url',   // URL profilu LinkedIn
        'facebook_url',   // URL profilu Facebook
        'youtube_url',    // URL kanału YouTube
        'x_com_url',      // URL profilu X.com (Twitter)
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
        return match ($this->gender) {
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
            'prefer_not_to_say' => 'Nie chcę określać',
        ];
    }

    /**
     * Relacja do kursów prowadzonych przez instruktora
     */
    public function courses()
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }

    public function primaryGrowthCampaigns(): HasMany
    {
        return $this->hasMany(GrowthCampaign::class, 'primary_instructor_id');
    }

    public function voiceGrowthCampaigns(): HasMany
    {
        return $this->hasMany(GrowthCampaign::class, 'communication_voice_instructor_id');
    }

    /**
     * Relacja do ankiet przypisanych do instruktora
     */
    public function instructorInvoices()
    {
        return $this->hasMany(InstructorInvoice::class, 'instructor_id');
    }

    public function surveys()
    {
        return $this->hasMany(Survey::class, 'instructor_id');
    }
}
