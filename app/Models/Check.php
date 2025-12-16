<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Check extends Model
{
    protected $fillable = [
        'link',
        'phone',
        'type',
        'result',
        'risk_score',
        'verdict'
    ];

    protected $casts = [
        'result' => 'array'
    ];
}
