<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivity;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CertificateTemplate extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'config',
        'preview_image',
        'is_active',
        'is_default'
    ];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean'
    ];

    /**
     * Relacja do kursów używających tego szablonu
     */
    public function courses()
    {
        return $this->hasMany(Course::class, 'certificate_template_id');
    }

    /**
     * Sprawdza czy plik Blade szablonu istnieje
     * Sprawdza lokalny katalog aplikacji (resources/views/certificates/)
     */
    public function bladeFileExists(): bool
    {
        if (!$this->slug) {
            return false;
        }

        $fileName = Str::slug($this->slug) . '.blade.php';
        $bladePath = resource_path('views/certificates/' . $fileName);

        return File::exists($bladePath);
    }
}
