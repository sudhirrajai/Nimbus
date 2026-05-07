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

        // 1. Static Menus
        $menus = [
            ['title' => 'Dashboard', 'url' => '/dashboard', 'icon' => 'dashboard', 'category' => 'Menu'],
            ['title' => 'Domains', 'url' => '/domains', 'icon' => 'language', 'category' => 'Menu'],
            ['title' => 'Databases', 'url' => '/database', 'icon' => 'database', 'category' => 'Menu'],
            ['title' => 'WordPress', 'url' => '/wordpress', 'icon' => 'description', 'category' => 'Menu'],
            ['title' => 'PHP Settings', 'url' => '/php', 'icon' => 'settings', 'category' => 'Menu'],
            ['title' => 'Nginx Config', 'url' => '/nginx', 'icon' => 'settings', 'category' => 'Menu'],
            ['title' => 'SSL Certificates', 'url' => '/ssl', 'icon' => 'lock', 'category' => 'Menu'],
            ['title' => 'Git Deployments', 'url' => '/deployments', 'icon' => 'git', 'category' => 'Menu'],
            ['title' => 'Supervisor', 'url' => '/supervisor', 'icon' => 'monitor', 'category' => 'Menu'],
            ['title' => 'File Manager', 'url' => '/domains', 'icon' => 'folder', 'category' => 'Menu'],
            ['title' => 'Profile', 'url' => '/profile', 'icon' => 'person', 'category' => 'Menu'],
            ['title' => 'System Logs', 'url' => '/logs', 'icon' => 'list', 'category' => 'Menu'],
        ];

        foreach ($menus as $menu) {
            if (stripos($menu['title'], $query) !== false) {
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
