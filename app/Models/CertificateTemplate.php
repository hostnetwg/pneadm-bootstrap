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
     * Sprawdza najpierw pakiet, potem aplikację (dla produkcji gdzie pliki są w resources/views/certificates)
     */
    public function bladeFileExists()
    {
        // 1. Sprawdź w pakiecie
        $packageView = "pne-certificate-generator::certificates.{$this->slug}";
        if (\Illuminate\Support\Facades\View::exists($packageView)) {
            return true;
        }
        
        // 2. Sprawdź w aplikacji (produkcja - pliki zapisane w resources/views/certificates)
        $appView = "certificates.{$this->slug}";
        if (\Illuminate\Support\Facades\View::exists($appView)) {
            return true;
        }
        
        // 3. Sprawdź bezpośrednio plik w systemie plików (fallback)
        $fileName = \Illuminate\Support\Str::slug($this->slug) . '.blade.php';
        
        // Sprawdź w aplikacji
        $appFilePath = resource_path('views/certificates/' . $fileName);
        if (\Illuminate\Support\Facades\File::exists($appFilePath)) {
            return true;
        }
        
        // Sprawdź w pakiecie (jeśli dostępny)
        $packagePath = $this->getPackagePathIfAvailable();
        if ($packagePath) {
            $packageFilePath = $packagePath . '/resources/views/certificates/' . $fileName;
            if (\Illuminate\Support\Facades\File::exists($packageFilePath)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Pobiera ścieżkę do pakietu jeśli jest dostępny (pomocnicza metoda)
     */
    protected function getPackagePathIfAvailable(): ?string
    {
        // Sprawdź różne możliwe lokalizacje pakietu
        $paths = [
            '/var/www/pne-certificate-generator',
            base_path('../pne-certificate-generator'),
            base_path('vendor/pne/certificate-generator'),
            '/home/hostnet/WEB-APP/pne-certificate-generator',
        ];
        
        foreach ($paths as $path) {
            $realPath = realpath($path);
            if ($realPath && \Illuminate\Support\Facades\File::exists($realPath . '/composer.json')) {
                return $realPath;
            }
        }
        
        return null;
    }
}
