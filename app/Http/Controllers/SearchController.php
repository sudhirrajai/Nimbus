<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WordPressSite;
use Illuminate\Support\Facades\DB;
use App\Models\GitDeployment;

class SearchController extends Controller
{
    /**
     * Search for menus, domains, and other resources.
     */
    public function search(Request $request)
    {
        $query = $request->query('q');
        if (!$query || strlen($query) < 2) {
            return response()->json([]);
        }

        $results = [];

        // 1. Static Menus (Comprehensive list from Sidebar)
        $menus = [
            // Server Management
            ['title' => 'Dashboard', 'url' => '/dashboard', 'icon' => 'dashboard', 'category' => 'Menu', 'description' => 'System overview and statistics'],
            ['title' => 'Domains', 'url' => '/domains', 'icon' => 'language', 'category' => 'Menu', 'description' => 'Manage websites and document roots'],
            ['title' => 'DNS Management', 'url' => '/dns', 'icon' => 'dns', 'category' => 'Menu', 'description' => 'Configure domain name records'],
            ['title' => 'Git Deployments', 'url' => '/deployments', 'icon' => 'rocket_launch', 'category' => 'Menu', 'description' => 'Automated deployment from GitHub/GitLab'],
            ['title' => 'Databases', 'url' => '/database', 'icon' => 'storage', 'category' => 'Menu', 'description' => 'MySQL/MariaDB databases and users'],
            ['title' => 'SSL Certificates', 'url' => '/ssl', 'icon' => 'lock', 'category' => 'Menu', 'description' => 'Manage HTTPS and Let\'s Encrypt'],
            ['title' => 'Nginx Configuration', 'url' => '/nginx', 'icon' => 'settings_ethernet', 'category' => 'Menu', 'description' => 'Custom server blocks and redirects'],
            ['title' => 'PHP Configuration', 'url' => '/php', 'icon' => 'code', 'category' => 'Menu', 'description' => 'Edit php.ini and manage extensions'],
            ['title' => 'WordPress', 'url' => '/wordpress', 'icon' => 'description', 'category' => 'Menu', 'description' => 'One-click install and management'],
            
            // Files & Resources
            ['title' => 'File Manager', 'url' => '/domains', 'icon' => 'folder', 'category' => 'Menu', 'description' => 'Browse and edit files directly'],
            ['title' => 'Backups', 'url' => '/backups', 'icon' => 'backup', 'category' => 'Menu', 'description' => 'Local and remote system backups'],
            ['title' => 'FTP Accounts', 'url' => '/ftp', 'icon' => 'cloud_upload', 'category' => 'Menu', 'description' => 'Manage secure file transfer users'],
            
            // Email
            ['title' => 'Email Accounts', 'url' => '/email', 'icon' => 'email', 'category' => 'Menu', 'description' => 'Create mailboxes and aliases'],
            
            // Automation
            ['title' => 'Supervisor', 'url' => '/supervisor', 'icon' => 'memory', 'category' => 'Menu', 'description' => 'Manage background worker processes'],
            ['title' => 'Cron Jobs', 'url' => '/cron', 'icon' => 'schedule', 'category' => 'Menu', 'description' => 'Schedule recurring system tasks'],
            
            // Monitoring
            ['title' => 'System Logs', 'url' => '/logs', 'icon' => 'list', 'category' => 'Menu', 'description' => 'View Nginx, PHP, and system errors'],
            ['title' => 'Resource Usage', 'url' => '/resources', 'icon' => 'monitoring', 'category' => 'Menu', 'description' => 'CPU, Memory, and Disk monitoring'],
            
            // Account & System
            ['title' => 'Profile Settings', 'url' => '/profile', 'icon' => 'person', 'category' => 'Menu', 'description' => 'Change password and account details'],
            ['title' => 'User Management', 'url' => '/users', 'icon' => 'group', 'category' => 'Menu', 'description' => 'Manage panel users and permissions'],
            ['title' => 'Panel Settings', 'url' => '/settings', 'icon' => 'settings', 'category' => 'Menu', 'description' => 'Nimbus global configuration'],
            ['title' => 'System Updates', 'url' => '/updates', 'icon' => 'system_update', 'category' => 'Menu', 'description' => 'Update Nimbus panel and core tools'],
            ['title' => 'Documentation', 'url' => '/documentation', 'icon' => 'article', 'category' => 'Menu', 'description' => 'Help guides and API docs'],
        ];

        foreach ($menus as $menu) {
            $matchTitle = stripos($menu['title'], $query) !== false;
            $matchDesc = isset($menu['description']) && stripos($menu['description'], $query) !== false;
            
            if ($matchTitle || $matchDesc) {
                $results[] = $menu;
            }
        }

        // 2. Domains (Try-catch in case table doesn't exist or permissions fail)
        try {
            $domains = DB::table('domains')
                ->where('domain', 'like', "%{$query}%")
                ->limit(5)
                ->get();

            foreach ($domains as $domain) {
                $results[] = [
                    'title' => $domain->domain,
                    'url' => "/file-manager/{$domain->domain}",
                    'icon' => 'language',
                    'category' => 'Domain'
                ];
            }
        } catch (\Exception $e) {
            // Silence DB errors in search
        }

        // 3. WordPress Sites
        try {
            $wpSites = WordPressSite::where('domain', 'like', "%{$query}%")
                ->orWhere('site_title', 'like', "%{$query}%")
                ->limit(5)
                ->get();

            foreach ($wpSites as $site) {
                $results[] = [
                    'title' => ($site->site_title ?: $site->domain),
                    'url' => "/wordpress",
                    'icon' => 'description',
                    'category' => 'WordPress'
                ];
            }
        } catch (\Exception $e) {
            // Silence
        }

        // 4. Git Deployments
        try {
            $deployments = GitDeployment::where('name', 'like', "%{$query}%")
                ->limit(5)
                ->get();

            foreach ($deployments as $deploy) {
                $results[] = [
                    'title' => $deploy->name,
                    'url' => "/deployments",
                    'icon' => 'git',
                    'category' => 'Deployment'
                ];
            }
        } catch (\Exception $e) {
            // Silence
        }

        return response()->json($results);
    }
}
