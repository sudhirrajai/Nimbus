<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class DomainController extends Controller
{
    private $basePath = '/var/www/';

    /**
     * Return all domain folders
     */
    public function index()
    {
        try {
            if (!File::exists($this->basePath)) {
                return response()->json([
                    'error' => "Base path {$this->basePath} does not exist on this system."
                ], 500);
            }

            $directories = collect(File::directories($this->basePath))
                ->map(function ($path) {
                    return basename($path);
                })
                ->filter(function ($name) {
                    // Ignore system directories
                    return !in_array($name, ['html', 'default', 'public', 'cgi-bin']);
                })
                ->values();

            return response()->json($directories);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load domains: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a new domain
     */
    public function store(Request $request)
    {
        try {
            // Validate domain format
            $request->validate([
                'domain' => [
                    'required',
                    'string',
                    'max:253',
                    'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/i'
                ]
            ], [
                'domain.required' => 'Domain name is required',
                'domain.regex' => 'Please enter a valid domain name (e.g., example.com)',
                'domain.max' => 'Domain name is too long (max 253 characters)'
            ]);

            if (!File::exists($this->basePath)) {
                return response()->json([
                    'error' => "Base path {$this->basePath} does not exist on this system."
                ], 500);
            }

            $domain = strtolower(trim($request->domain));
            $path = $this->basePath . $domain;

            // Check if domain already exists
            if (File::exists($path)) {
                return response()->json([
                    'error' => 'Domain already exists'
                ], 409);
            }

            // Create folder structure
            File::makeDirectory($path, 0755, true);
            File::makeDirectory("$path/public", 0755, true);
            File::makeDirectory("$path/logs", 0755, true);

            // Create basic index file
            File::put("$path/public/index.php", $this->getDefaultIndexContent($domain));

            // Create .env file placeholder
            File::put("$path/.env", "APP_ENV=production\nAPP_DEBUG=false\n");

            // Log the creation
            \Log::info("Domain created: $domain by user " . auth()->id());

            return response()->json([
                'message' => 'Domain created successfully',
                'domain' => $domain
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'error' => $e->validator->errors()->first('domain')
            ], 422);
        } catch (\Exception $e) {
            \Log::error("Failed to create domain: " . $e->getMessage());
            return response()->json([
                'error' => 'Failed to create domain: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Edit domain → rename folder
     */
    public function update(Request $request, $oldDomain)
    {
        try {
            // Validate domain format
            $request->validate([
                'domain' => [
                    'required',
                    'string',
                    'max:253',
                    'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/i'
                ]
            ], [
                'domain.required' => 'Domain name is required',
                'domain.regex' => 'Please enter a valid domain name (e.g., example.com)',
                'domain.max' => 'Domain name is too long (max 253 characters)'
            ]);

            $oldPath = $this->basePath . $oldDomain;
            $newDomain = strtolower(trim($request->domain));
            $newPath = $this->basePath . $newDomain;

            // Check if old domain exists
            if (!File::exists($oldPath)) {
                return response()->json([
                    'error' => 'Domain not found'
                ], 404);
            }

            // If domain name unchanged, return success
            if ($oldDomain === $newDomain) {
                return response()->json([
                    'message' => 'Domain unchanged',
                    'domain' => $newDomain
                ]);
            }

            // Check if new domain already exists
            if (File::exists($newPath)) {
                return response()->json([
                    'error' => 'New domain name already exists'
                ], 409);
            }

            // Rename the directory
            File::move($oldPath, $newPath);

            // Log the update
            \Log::info("Domain updated: $oldDomain -> $newDomain by user " . auth()->id());

            return response()->json([
                'message' => 'Domain updated successfully',
                'domain' => $newDomain
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'error' => $e->validator->errors()->first('domain')
            ], 422);
        } catch (\Exception $e) {
            \Log::error("Failed to update domain: " . $e->getMessage());
            return response()->json([
                'error' => 'Failed to update domain: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete domain folder
     */
    public function destroy($domain)
    {
        try {
            $path = $this->basePath . $domain;

            if (!File::exists($path)) {
                return response()->json([
                    'error' => 'Domain not found'
                ], 404);
            }

            // Delete the directory and all its contents
            File::deleteDirectory($path);

            // Log the deletion
            \Log::info("Domain deleted: $domain by user " . auth()->id());

            return response()->json([
                'message' => 'Domain deleted successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error("Failed to delete domain: " . $e->getMessage());
            return response()->json([
                'error' => 'Failed to delete domain: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate default index.php content
     */
    private function getDefaultIndexContent($domain)
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to $domain</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .container {
            text-align: center;
            padding: 2rem;
        }
        h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Welcome to $domain</h1>
        <p>Your domain is ready! Start building something amazing.</p>
        <p style="font-size: 0.9rem; margin-top: 2rem;">Powered by Nimbus Control Panel</p>
    </div>
</body>
</html>
HTML;
    }
}