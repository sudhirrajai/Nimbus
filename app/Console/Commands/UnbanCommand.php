<?php

namespace App\Console\Commands;

use App\Models\SecurityRule;
use App\Services\LoginSecurityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class UnbanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nimbus:unban 
                            {--ip= : Specific IP address to unban} 
                            {--all : Clear all active IP rate limits and temporary bans}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unban an IP address or clear all active login rate limits and temporary blocks';

    /**
     * Execute the console command.
     */
    public function handle(LoginSecurityService $securityService): int
    {
        $all = $this->option('all');
        $ip = $this->option('ip');

        if ($all) {
            // Remove all blocked rules from security_rules
            $deleted = SecurityRule::where('type', 'block')->delete();
            
            // Clear application cache
            Cache::flush();

            $this->info("--------------------------------------------------");
            $this->info(" All Rate Limits & Temporary Bans Cleared");
            $this->info("--------------------------------------------------");
            $this->line(" Removed {$deleted} permanent blacklist rule(s).");
            $this->line(" Cleared all temporary IP brute-force lockouts.");
            $this->info("All client IPs can now access the panel.");

            return Command::SUCCESS;
        }

        if (empty($ip)) {
            $ip = $this->ask('Enter the IP address to unban');
        }

        $ip = trim((string) $ip);

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->error("Invalid IP address: '{$ip}'");
            return Command::FAILURE;
        }

        $securityService->unbanIp($ip);

        $this->info("--------------------------------------------------");
        $this->info(" IP Successfully Unbanned");
        $this->info("--------------------------------------------------");
        $this->line(" Target IP : {$ip}");
        $this->info("The rate limits and blocklists for this IP have been completely lifted.");

        return Command::SUCCESS;
    }
}
