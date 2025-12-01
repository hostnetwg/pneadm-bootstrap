<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivity;

class CertificateTemplate extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'config',
        'preview_image',
        'is_active'
    ];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean'
    ];

    /**
     * Relacja do kursów używających tego szablonu
     */
    public function courses()
    {
        return $this->hasMany(Course::class, 'certificate_template_id');
    }

    /**
     * Zwraca ścieżkę do pliku blade szablonu
     * Zawsze z pakietu - szablony nie są już przechowywane lokalnie
     */
    public function getBladePathAttribute()
    {
        // Zawsze używaj pakietu
        return "pne-certificate-generator::certificates.{$this->slug}";
    }

    /**
     * Sprawdza czy plik blade szablonu istnieje
     * Sprawdza TYLKO w pakiecie - szablony nie są już przechowywane lokalnie
     */
    public function bladeFileExists()
    {
        // Sprawdź tylko w pakiecie
        $packageView = "pne-certificate-generator::certificates.{$this->slug}";
        return \Illuminate\Support\Facades\View::exists($packageView);
    }
}
