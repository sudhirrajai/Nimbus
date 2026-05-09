<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecurityThreat extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_path',
        'type',
        'status',
        'details',
        'detected_at',
        'resolved_at'
    ];

    protected $casts = [
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime'
    ];
}
