<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZamowieniaProd extends Model
{
    use HasFactory;

    protected $table = 'zamowienia_PROD';
    protected $connection = 'mysql_certgen';
    public $timestamps = true;

    protected $fillable = [
        'id',
        'idProdPubligo',
        'price_id_ProdPubligo',
        'status',
        'nazwa',
        'promocja'
    ];

    protected $casts = [
        'idProdPubligo' => 'integer',
        'price_id_ProdPubligo' => 'integer',
        'status' => 'integer'
    ];
}
