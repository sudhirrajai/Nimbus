<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DomainCloudflareSetting extends Model
{
    protected $fillable = [
        'domain',
        'api_token',
        'zone_id'
    ];

    protected $casts = [
        'api_token' => 'encrypted'
    ];
}
