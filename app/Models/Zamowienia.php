<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zamowienia extends Model
{
    use HasFactory;

    protected $table = 'zamowienia';
    protected $connection = 'mysql_certgen';
    public $timestamps = true;

    protected $fillable = [
        'id',
        'id_zam',
        'data_wplaty',
        'imie',
        'nazwisko',
        'email',
        'kod',
        'poczta',
        'adres',
        'produkt_id',
        'produkt_nazwa',
        'produkt_cena',
        'wysylka',
        'id_edu',
        'NR',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'data_wplaty' => 'datetime',
        'produkt_cena' => 'float',
        'wysylka' => 'integer',
        'id_edu' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
}
