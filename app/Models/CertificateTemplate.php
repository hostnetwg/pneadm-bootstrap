<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CertificateTemplate extends Model
{
    use HasFactory;

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
     */
    public function getBladePathAttribute()
    {
        return "certificates.{$this->slug}";
    }

    /**
     * Sprawdza czy plik blade szablonu istnieje
     */
    public function bladeFileExists()
    {
        $path = resource_path("views/certificates/{$this->slug}.blade.php");
        return file_exists($path);
    }
}
