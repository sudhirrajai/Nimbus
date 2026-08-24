<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class BackupSchedule extends Model
{
    protected $table = 'backup_schedules';

    protected $fillable = [
        'name',
        'domain',
        'database_name',
        'type',
        'frequency',
        'time',
        'day_of_week',
        'day_of_month',
        'retention_count',
        'storage_driver',
        'storage_config',
        'email_notifications',
        'is_active',
        'last_run_at',
        'next_run_at',
        'last_status',
        'last_error',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'day_of_month' => 'integer',
        'retention_count' => 'integer',
        'storage_config' => 'array',
        'email_notifications' => 'boolean',
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public function records(): HasMany
    {
        return $this->hasMany(BackupRecord::class, 'schedule_id');
    }

    /**
     * Calculate and set the next execution timestamp based on frequency & time
     */
    public function calculateNextRun(): Carbon
    {
        $timeParts = explode(':', $this->time ?: '02:00');
        $hour = (int) ($timeParts[0] ?? 2);
        $minute = (int) ($timeParts[1] ?? 0);

        $now = Carbon::now();

        switch ($this->frequency) {
            case 'hourly':
                $next = $now->copy()->addHour()->minute($minute)->second(0);
                break;

            case 'daily':
                $next = $now->copy()->hour($hour)->minute($minute)->second(0);
                if ($next->isPast()) {
                    $next->addDay();
                }
                break;

            case 'weekly':
                $dayOfWeek = $this->day_of_week !== null ? (int)$this->day_of_week : Carbon::SUNDAY;
                $next = $now->copy()->next($dayOfWeek)->hour($hour)->minute($minute)->second(0);
                break;

            case 'monthly':
                $dayOfMonth = $this->day_of_month !== null ? (int)$this->day_of_month : 1;
                $next = $now->copy()->day(min($dayOfMonth, $now->daysInMonth))->hour($hour)->minute($minute)->second(0);
                if ($next->isPast()) {
                    $next->addMonthNoOverflow();
                    $next->day(min($dayOfMonth, $next->daysInMonth));
                }
                break;

            default:
                $next = $now->copy()->addDay()->hour($hour)->minute($minute)->second(0);
                break;
        }

        $this->next_run_at = $next;
        $this->save();

        return $next;
    }
}
