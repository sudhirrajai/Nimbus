<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    protected $fillable = [
        'user_id',
        'email',
        'action',
        'service',
        'description',
        'ip_address',
        'user_agent',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Helper to easily write log entry
     */
    public static function log(string $action, string $service, string $description)
    {
        return self::create([
            'user_id' => auth()->id(),
            'email' => auth()->user()?->email,
            'action' => $action,
            'service' => $service,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
