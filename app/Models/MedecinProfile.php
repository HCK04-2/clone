<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedecinProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'specialty',
        'experience_years',
        'horaires',
        'diplomas',
        'adresse',
        'disponible',
        'absence_start_date',
        'absence_end_date'
    ];
}
