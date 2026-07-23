<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class CleanActivityLogs extends Command
{
    protected $signature = 'activity-log:clean {--days= : Override configured retention days}';
    protected $description = 'Clean activity logs older than specified retention days';

    public function handle()
    {
        $overrideDays = $this->option('days');

        if ($overrideDays !== null) {
            $days = (int) $overrideDays;
        } else {
            $autoClean = Setting::where('key', 'activity_log_auto_clean')->value('value') ?? '1';
            if ($autoClean === '0') {
                $this->info('Auto cleanup of activity logs is disabled in settings.');
                return 0;
            }
            $days = (int) (Setting::where('key', 'activity_log_retention_days')->value('value') ?? 30);
        }

        if ($days <= 0) {
            $this->info('Activity log retention is set to keep forever (0 days). No records deleted.');
            return 0;
        }

        $cutoff = now()->subDays($days);
        $count = ActivityLog::where('created_at', '<', $cutoff)->delete();

        $message = "Cleaned {$count} activity log record(s) older than {$days} day(s) (before {$cutoff->toDateTimeString()}).";
        $this->info($message);
        Log::info($message);

        return 0;
    }
}
