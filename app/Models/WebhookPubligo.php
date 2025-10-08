<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookPubligo extends Model
{
    use HasFactory;

    protected $table = 'webhook_publigo';
    protected $connection = 'mysql_certgen';
    public $timestamps = true; // Włączamy automatyczne timestamps

    protected $fillable = [
        'id',
        'id_produktu',
        'data',
        'id_sendy',
        'clickmeeting',
        'temat',
        'instruktor',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'data' => 'datetime',
        'clickmeeting' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
}
