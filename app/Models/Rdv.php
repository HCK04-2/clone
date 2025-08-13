<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rdv extends Model
{
    use HasFactory;

    protected $table = 'rdv';

    protected $fillable = [
        'patient_id',
        'target_user_id',
        'target_role',
        'date_time',
        'status',
        'reason',
        'notes',
    ];

    protected $casts = [
        'date_time' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function target()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
