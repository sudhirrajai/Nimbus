<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\LicenseService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    /**
     * Handle incoming bug report, bundle licensing context, and forward to VmCoreCentral.
     */
    public function store(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'screenshot' => 'nullable|file|image|max:10240', // Max 10MB
            'images' => 'nullable|array',
            'images.*' => 'file|image|max:10240',
        ]);

        $licenseService = app(LicenseService::class);
        $licenseKey = $licenseService->getLicenseKey();
        
        $user = auth()->user();

        $apiUrl = rtrim(config('services.vmcore.api_url', 'http://localhost:8001'), '/') . '/api/v1/bug-reports';

        // Prepare multipart HTTP client request
        $http = Http::timeout(25)->asMultipart();

        // Attach screenshot
        if ($request->hasFile('screenshot')) {
            $file = $request->file('screenshot');
            $http->attach(
                'screenshot',
                file_get_contents($file->getRealPath()),
                $file->getClientOriginalName(),
                ['Content-Type' => $file->getClientMimeType()]
            );
        }

        // Attach other images
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $i => $file) {
                $http->attach(
                    "images[$i]",
                    file_get_contents($file->getRealPath()),
                    $file->getClientOriginalName(),
                    ['Content-Type' => $file->getClientMimeType()]
                );
            }
        }

        try {
            $response = $http->post($apiUrl, [
                'license_key' => $licenseKey,
                'domain'      => $request->getHost(),
                'admin_name'  => $user ? $user->name : 'Nimbus Admin',
                'admin_email' => $user ? $user->email : null,
                'message'     => $request->input('message'),
                'ip_address'  => request()->server('SERVER_ADDR') ?? (gethostbyname(gethostname()) ?: '127.0.0.1'),
            ]);

            if ($response->successful()) {
                return response()->json([
                    'status' => true,
                    'message' => 'Bug report submitted successfully.'
                ]);
            }

            Log::error('VmCoreCentral rejected bug report: ' . $response->body());
            $errorMsg = $response->json('message') ?? 'Unknown server error.';
            return response()->json([
                'status' => false,
                'message' => 'Licensing server error: ' . $errorMsg
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Failed to forward bug report: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Connection failed: Unable to reach the licensing server.'
            ], 500);
        }
    }
}
