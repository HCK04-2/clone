<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PsychologueProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'specialty', // Add this line
        'experience_years',
        'horaires',
        'diplomas',
        'adresse',
        'disponible',
        'absence_start_date',
        'absence_end_date',
    ];
}
