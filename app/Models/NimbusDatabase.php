<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NimbusDatabase extends Model
{
    protected $table = 'nimbus_databases';
    protected $fillable = ['name', 'created_by'];
}
