<?php

namespace App\Console\Commands;

use App\Models\RecycleBinItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PurgeTrashCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nimbus:purge-trash {--days=30 : Number of days to retain items in trash}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permanently delete items in the recycle bin older than specified days (default 30 days)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        if ($days <= 0) {
            $days = 30;
        }

        $threshold = now()->subDays($days);
        $this->info("Purging trash items deleted before: {$threshold->toDateTimeString()} ({$days} days retention)...");

        $expiredItems = RecycleBinItem::where('created_at', '<', $threshold)->get();
        $count = $expiredItems->count();

        if ($count === 0) {
            $this->info("No expired trash items to purge.");
            return Command::SUCCESS;
        }

        $deletedCount = 0;
        foreach ($expiredItems as $item) {
            try {
                if (file_exists($item->trash_path)) {
                    $escaped = escapeshellarg($item->trash_path);
                    exec("sudo rm -rf {$escaped} 2>&1");
                }
                $item->delete();
                $deletedCount++;
            } catch (\Exception $e) {
                $this->error("Failed to purge item #{$item->id} ({$item->original_name}): " . $e->getMessage());
            }
        }

        $this->info("Successfully purged {$deletedCount} of {$count} expired trash item(s).");
        return Command::SUCCESS;
    }
}
