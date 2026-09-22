<?php

namespace App\Http\Controllers;

use App\Services\RedisService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class RedisController extends Controller
{
    /**
     * Display Redis Manager Dashboard.
     */
    public function index()
    {
        $installed = RedisService::isInstalled();
        $status = RedisService::getServiceStatus();
        $enabled = RedisService::isEnabled();
        $info = RedisService::getInfo();
        $config = RedisService::getConfig();

        return Inertia::render('Redis/Index', [
            'is_installed' => $installed,
            'status' => $status,
            'is_enabled' => $enabled,
            'initial_info' => $info,
            'initial_config' => $config,
        ]);
    }

    /**
     * Live status polling endpoint.
     */
    public function status()
    {
        return response()->json([
            'is_installed' => RedisService::isInstalled(),
            'status' => RedisService::getServiceStatus(),
            'is_enabled' => RedisService::isEnabled(),
            'info' => RedisService::getInfo(),
            'config' => RedisService::getConfig(),
        ]);
    }

    /**
     * 1-Click Install Redis.
     */
    public function install(Request $request)
    {
        $user = auth()->user();
        if ($user && $user->role !== 'root') {
            return response()->json(['error' => 'Only Super Admin can install system packages.'], 403);
        }

        $res = RedisService::install();
        return response()->json($res, $res['success'] ? 200 : 500);
    }

    /**
     * Control redis-server systemd daemon.
     */
    public function serviceAction(Request $request, string $action)
    {
        $user = auth()->user();
        if ($user && !in_array($user->role, ['root', 'admin'])) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $res = RedisService::serviceAction($action);
        return response()->json($res, $res['success'] ? 200 : 500);
    }

    /**
     * Update Redis memory allocation and general configuration.
     */
    public function updateConfig(Request $request)
    {
        $request->validate([
            'maxmemory' => 'nullable|string|max:20',
            'maxmemory_policy' => 'nullable|string|in:allkeys-lru,volatile-lru,allkeys-lfu,volatile-lfu,allkeys-random,volatile-random,volatile-ttl,noeviction',
            'bind' => 'nullable|string|max:100',
            'protected_mode' => 'nullable|string|in:yes,no',
        ]);

        $res = RedisService::updateConfig($request->only([
            'maxmemory', 'maxmemory_policy', 'bind', 'protected_mode'
        ]));

        return response()->json($res);
    }

    /**
     * Set, generate, or remove Redis password.
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'password' => 'nullable|string|max:128',
            'generate' => 'nullable|boolean'
        ]);

        $password = $request->input('password', '');
        if ($request->boolean('generate')) {
            $password = Str::random(32);
        }

        $res = RedisService::updateConfig(['password' => $password]);
        $res['generated_password'] = $request->boolean('generate') ? $password : null;

        return response()->json($res);
    }

    /**
     * Search and list keys with pattern and pagination.
     */
    public function getKeys(Request $request)
    {
        $pattern = $request->input('pattern', '*');
        $db = (int)$request->input('db', 0);
        $limit = min(150, max(10, (int)$request->input('limit', 50)));

        $data = RedisService::getKeys($pattern, $db, $limit);
        return response()->json($data);
    }

    /**
     * View decoded key value and TTL.
     */
    public function viewKey(Request $request)
    {
        $request->validate([
            'key' => 'required|string',
            'db' => 'nullable|integer|min:0|max:15'
        ]);

        $key = $request->input('key');
        $db = (int)$request->input('db', 0);

        $data = RedisService::getKeyValue($key, $db);
        return response()->json($data);
    }

    /**
     * Delete a single key.
     */
    public function deleteKey(Request $request)
    {
        $request->validate([
            'key' => 'required|string',
            'db' => 'nullable|integer|min:0|max:15'
        ]);

        $key = $request->input('key');
        $db = (int)$request->input('db', 0);

        $deleted = RedisService::deleteKey($key, $db);
        return response()->json([
            'success' => $deleted,
            'message' => $deleted ? "Key '{$key}' deleted successfully." : "Key could not be deleted."
        ]);
    }

    /**
     * Flush keys matching a prefix / pattern (Safe cache flush).
     */
    public function flushPattern(Request $request)
    {
        $request->validate([
            'pattern' => 'required|string|min:2|max:100',
            'db' => 'nullable|integer|min:0|max:15'
        ]);

        $pattern = $request->input('pattern');
        $db = (int)$request->input('db', 0);

        $res = RedisService::flushPattern($pattern, $db);
        return response()->json($res);
    }

    /**
     * Flush database or all databases.
     */
    public function flushDb(Request $request)
    {
        $db = (int)$request->input('db', 0);
        $all = $request->boolean('all');

        if ($all) {
            $res = RedisService::flushAll();
        } else {
            $res = RedisService::flushDb($db);
        }

        return response()->json($res);
    }

    /**
     * Retrieve SlowLog entries.
     */
    public function getSlowLog(Request $request)
    {
        $limit = min(50, max(5, (int)$request->input('limit', 25)));
        $entries = RedisService::getSlowLog($limit);
        return response()->json(['slowlog' => $entries]);
    }

    /**
     * Retrieve connected clients.
     */
    public function getClients()
    {
        $clients = RedisService::getClients();
        return response()->json(['clients' => $clients]);
    }
}
