<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class RollbackCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nimbus:rollback 
                            {snapshot? : Specific snapshot name (e.g. snapshot_20261014_201838)}
                            {--list : List all available snapshots}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely rollback Nimbus to a pre-update snapshot backup';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $snapshotsDir = '/var/backups/nimbus_snapshots';

        if (!is_dir($snapshotsDir)) {
            $this->error("No snapshots directory found at {$snapshotsDir}.");
            return 1;
        }

        $snapshots = glob("{$snapshotsDir}/snapshot_*", GLOB_ONLYDIR);
        if (empty($snapshots)) {
            $this->error("No pre-update snapshots found in {$snapshotsDir}.");
            return 1;
        }

        // Sort descending by timestamp in folder name
        rsort($snapshots);

        if ($this->option('list')) {
            $this->info("Available Pre-Update Snapshots:");
            foreach ($snapshots as $snap) {
                $name = basename($snap);
                $version = file_exists("{$snap}/VERSION") ? trim(file_get_contents("{$snap}/VERSION")) : 'Unknown';
                $date = date('Y-m-d H:i:s', filemtime($snap));
                $this->line(" - <comment>{$name}</comment> (v{$version}, {$date})");
            }
            return 0;
        }

        $scriptPath = base_path('bin/nimbus-rollback');
        if (!file_exists($scriptPath)) {
            $this->error("Rollback helper script not found at {$scriptPath}.");
            return 1;
        }

        $snapshotArg = $this->argument('snapshot') ?? '';
        $cmd = ["sudo", "bash", $scriptPath];
        if (!empty($snapshotArg)) {
            $cmd[] = $snapshotArg;
        }

        $this->warn("Initiating safe rollback process...");

        $process = new Process($cmd);
        $process->setTimeout(300);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        return $process->isSuccessful() ? 0 : 1;
    }
}
