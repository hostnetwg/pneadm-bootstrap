<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Publigo extends Model
{
    use HasFactory;

    protected $table = 'publigo'; // Nazwa tabeli w bazie danych

    protected $connection = 'mysql_certgen'; // Połączenie z bazą certgen

    protected $fillable = [
        'id_old',
        'title',
        'description',
        'start_date',
        'end_date',
        'is_paid',
        'type',
        'category',
        'instructor_id',
        'image',
        'is_active',
        'certificate_format',
        'platform',
        'meeting_link',
        'meeting_password',
        'location_name',
        'postal_code',
        'post_office',
        'address',
        'country',
    ];

    public function instructor()
    {
        return $this->belongsTo(Instructor::class, 'instructor_id');
    }
    
    /**
     * Relacja do modelu `Course`
     */
    public function course()
    {
        return $this->hasOne(Course::class, 'id_old', 'id_old');
    }
}
