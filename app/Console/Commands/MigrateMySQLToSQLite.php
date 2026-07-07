<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateMySQLToSQLite extends Command
{
    protected $signature = 'db:migrate-mysql-to-sqlite';
    protected $description = 'Migrates Nimbus production data from MySQL to SQLite';

    public function handle()
    {
        $this->info('Starting database migration to SQLite...');

        // Override database configuration dynamically to point to the correct sqlite file
        config(['database.connections.sqlite.database' => '/usr/local/nimbus/database/database.sqlite']);

        // 1. Ensure SQLite database is fully migrated (creates identical tables)
        $this->call('migrate', [
            '--database' => 'sqlite',
            '--force' => true
        ]);

        // 2. Disable foreign key checks on SQLite during data insert
        DB::connection('sqlite')->statement('PRAGMA foreign_keys = OFF;');

        $tables = [
            'users', 'settings', 'email_accounts', 'email_aliases', 'email_domains',
            'git_deployments', 'deployment_logs', 'command_blacklist', 'system_users',
            'security_rules', 'wordpress_sites', 'domain_cloudflare_settings',
            'user_websites', 'security_threats', 'nimbus_databases', 'activity_logs'
        ];

        foreach ($tables as $table) {
            if (!Schema::connection('mysql')->hasTable($table)) {
                continue;
            }

            $this->info("Migrating table: {$table}...");
            
            // Clear existing records in SQLite table to avoid duplicate keys
            DB::connection('sqlite')->table($table)->truncate();

            // Get columns existing in target SQLite table
            $columns = Schema::connection('sqlite')->getColumnListing($table);

            // Fetch records from MySQL and insert into SQLite in chunks
            DB::connection('mysql')->table($table)->orderBy('id')->chunk(100, function ($rows) use ($table, $columns) {
                $data = [];
                foreach ($rows as $row) {
                    $filteredRow = [];
                    foreach ($columns as $col) {
                        if (property_exists($row, $col)) {
                            $filteredRow[$col] = $row->{$col};
                        }
                    }
                    $data[] = $filteredRow;
                }

                if (!empty($data)) {
                    DB::connection('sqlite')->table($table)->insert($data);
                }
            });
        }

        // 3. Re-enable foreign key checks
        DB::connection('sqlite')->statement('PRAGMA foreign_keys = ON;');

        $this->info('Migration completed successfully!');
    }
}
