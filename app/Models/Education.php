<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Education extends Model
{
    use HasFactory;

    // nazwa tabeli w bazie danych certgen
    protected $table = 'education';

    // wskazanie połączenia do drugiej bazy danych
    protected $connection = 'mysql_certgen';

    // opcjonalnie, określ, które pola mogą być uzupełniane masowo (mass assignment)
    protected $fillable = ['lp', 'title', 'description', 'data'];

    // Jeśli tabela nie używa standardowych pól timestamps (created_at, updated_at), dodaj poniższą linię
    public $timestamps = false;
}
