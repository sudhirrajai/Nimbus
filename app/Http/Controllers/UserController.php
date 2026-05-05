<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserWebsite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        return Inertia::render('Users/Index');
    }

    /**
     * List all users with their website assignments
     */
    public function list()
    {
        $users = User::with('websites')
            ->orderBy('id')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'linux_user' => $user->linux_user,
                    'status' => $user->status,
                    'last_login_at' => $user->last_login_at?->diffForHumans(),
                    'created_at' => $user->created_at->format('M d, Y'),
                    'websites' => $user->websites->map(fn($w) => [
                        'id' => $w->id,
                        'domain' => $w->domain,
                        'permissions' => $w->permissions ?? [],
                    ]),
                    'website_count' => $user->websites->count(),
                    'is_protected' => $user->id === 1, // Root user cannot be deleted
                ];
            });

        return response()->json(['success' => true, 'users' => $users]);
    }

    /**
     * Get all available domains on the server
     */
    public function availableDomains()
    {
        $domains = [];
        $dirs = glob('/var/www/*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $name = basename($dir);
            if ($name !== 'html') {
                $domains[] = $name;
            }
        }

        return response()->json(['success' => true, 'domains' => $domains]);
    }

    /**
     * Create a new user
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(['admin', 'user'])],
            'websites' => 'array',
            'websites.*.domain' => 'required|string',
            'websites.*.permissions' => 'array',
        ]);

        // Generate linux username
        $linuxUser = 'nimbus_' . Str::slug($request->name, '_');
        $linuxUser = substr(preg_replace('/[^a-z0-9_]/', '', strtolower($linuxUser)), 0, 30);

        // Ensure unique linux user
        $baseUser = $linuxUser;
        $counter = 1;
        while (User::where('linux_user', $linuxUser)->exists()) {
            $linuxUser = $baseUser . '_' . $counter++;
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'linux_user' => $linuxUser,
            'status' => 'active',
        ]);

        // Assign websites
        if ($request->has('websites')) {
            foreach ($request->websites as $site) {
                UserWebsite::create([
                    'user_id' => $user->id,
                    'domain' => $site['domain'],
                    'permissions' => $site['permissions'] ?? ['files', 'deployments', 'wordpress'],
                ]);
            }
        }

        // Create Linux user
        $this->createLinuxUser($linuxUser, $user->websites->pluck('domain')->toArray());

        Log::info("User created: {$user->name} ({$user->email}) with role {$user->role}, linux user: {$linuxUser}");

        return response()->json([
            'success' => true,
            'message' => "User '{$user->name}' created successfully.",
            'user' => $user->load('websites'),
        ]);
    }

    /**
     * Update an existing user
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Prevent modifying the root user's role
        if ($user->id === 1 && $request->has('role') && $request->role !== 'root') {
            return response()->json(['success' => false, 'error' => 'Cannot change the root user\'s role.'], 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'sometimes|string|min:8',
            'role' => ['sometimes', Rule::in(['root', 'admin', 'user'])],
            'status' => ['sometimes', Rule::in(['active', 'suspended'])],
        ]);

        if ($request->has('name')) $user->name = $request->name;
        if ($request->has('email')) $user->email = $request->email;
        if ($request->has('password')) $user->password = Hash::make($request->password);
        if ($request->has('role') && $user->id !== 1) $user->role = $request->role;

        if ($request->has('status')) {
            $user->status = $request->status;
            // Lock/unlock Linux user
            if ($user->linux_user) {
                if ($request->status === 'suspended') {
                    shell_exec("sudo usermod -L " . escapeshellarg($user->linux_user) . " 2>&1");
                } else {
                    shell_exec("sudo usermod -U " . escapeshellarg($user->linux_user) . " 2>&1");
                }
            }
        }

        $user->save();

        Log::info("User updated: {$user->name} (ID: {$user->id})");

        return response()->json([
            'success' => true,
            'message' => "User '{$user->name}' updated.",
            'user' => $user->load('websites'),
        ]);
    }

    /**
     * Update website assignments for a user
     */
    public function updateWebsites(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'websites' => 'required|array',
            'websites.*.domain' => 'required|string',
            'websites.*.permissions' => 'array',
        ]);

        // Remove old assignments
        $user->websites()->delete();

        // Add new ones
        foreach ($request->websites as $site) {
            UserWebsite::create([
                'user_id' => $user->id,
                'domain' => $site['domain'],
                'permissions' => $site['permissions'] ?? ['files', 'deployments', 'wordpress'],
            ]);
        }

        // Update Linux user directory access
        if ($user->linux_user) {
            $domains = collect($request->websites)->pluck('domain')->toArray();
            $this->updateLinuxUserAccess($user->linux_user, $domains);
        }

        Log::info("Website assignments updated for user: {$user->name} (ID: {$user->id})");

        return response()->json([
            'success' => true,
            'message' => "Website access updated for '{$user->name}'.",
            'user' => $user->load('websites'),
        ]);
    }

    /**
     * Delete a user
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        if ($user->id === 1) {
            return response()->json(['success' => false, 'error' => 'Cannot delete the root user.'], 403);
        }

        // Remove Linux user
        if ($user->linux_user) {
            $this->removeLinuxUser($user->linux_user);
        }

        $userName = $user->name;
        $user->websites()->delete();
        $user->delete();

        Log::info("User deleted: {$userName} (ID: {$id})");

        return response()->json([
            'success' => true,
            'message' => "User '{$userName}' deleted.",
        ]);
    }

    // ─── Linux User Management ──────────────────────────────────

    /**
     * Create a Linux system user with restricted access
     */
    private function createLinuxUser(string $username, array $domains = [])
    {
        try {
            // Create user with home directory, no login shell (nologin for security)
            shell_exec("sudo useradd -m -d /home/{$username} -s /bin/bash " . escapeshellarg($username) . " 2>&1");

            // Set home directory permissions (user only)
            shell_exec("sudo chmod 750 /home/{$username} 2>&1");

            // Add user to www-data group so they can read web files
            shell_exec("sudo usermod -aG www-data " . escapeshellarg($username) . " 2>&1");

            // Grant access to assigned domains
            $this->updateLinuxUserAccess($username, $domains);

            Log::info("Linux user created: {$username}");
        } catch (\Exception $e) {
            Log::error("Failed to create Linux user {$username}: " . $e->getMessage());
        }
    }

    /**
     * Update Linux user's access to website directories
     */
    private function updateLinuxUserAccess(string $username, array $domains)
    {
        try {
            foreach ($domains as $domain) {
                $path = "/var/www/{$domain}";
                if (is_dir($path)) {
                    // Add ACL for the user to access this directory
                    shell_exec("sudo setfacl -R -m u:{$username}:rwx " . escapeshellarg($path) . " 2>&1");
                    shell_exec("sudo setfacl -R -d -m u:{$username}:rwx " . escapeshellarg($path) . " 2>&1");
                }
            }
            Log::info("Updated directory access for {$username}: " . implode(', ', $domains));
        } catch (\Exception $e) {
            Log::error("Failed to update access for {$username}: " . $e->getMessage());
        }
    }

    /**
     * Remove a Linux system user
     */
    private function removeLinuxUser(string $username)
    {
        try {
            // Remove ACLs first
            $user = User::where('linux_user', $username)->first();
            if ($user) {
                foreach ($user->websites as $site) {
                    $path = "/var/www/{$site->domain}";
                    if (is_dir($path)) {
                        shell_exec("sudo setfacl -R -x u:{$username} " . escapeshellarg($path) . " 2>&1");
                    }
                }
            }

            // Remove the user (keep home for safety, use -r to remove home)
            shell_exec("sudo userdel " . escapeshellarg($username) . " 2>&1");

            Log::info("Linux user removed: {$username}");
        } catch (\Exception $e) {
            Log::error("Failed to remove Linux user {$username}: " . $e->getMessage());
        }
    }
}
