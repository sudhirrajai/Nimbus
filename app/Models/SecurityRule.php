<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityRule extends Model
{
    protected $fillable = ['ip_address', 'type', 'is_active', 'description'];
}
