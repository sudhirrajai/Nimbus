<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BackupService;

class RunScheduledBackups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nimbus:backups-run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process and execute due automated backup schedules';

    /**
     * Execute the console command.
     */
    public function handle(BackupService $backupService): int
    {
        $this->info("Checking for due backup schedules...");
        $executed = $backupService->runScheduledBackups();
        $this->info("Completed. Executed {$executed} backup schedule(s).");

        return Command::SUCCESS;
    }
}
