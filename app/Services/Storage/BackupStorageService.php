<?php

namespace App\Services\Storage;

use App\Models\BackupDestination;
use App\Models\BackupRecord;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackupStorageService
{
    /**
     * Test connection to a backup destination
     */
    public function testConnection(BackupDestination $destination): array
    {
        try {
            $driver = $destination->driver;
            $creds = $destination->credentials ?: [];

            if ($driver === 'local') {
                $dir = \App\Services\BackupService::getBackupDirectory();
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                if (PHP_OS_FAMILY === 'Linux') {
                    if (!is_dir($dir) || !is_writable($dir)) {
                        exec("sudo mkdir -p " . escapeshellarg($dir) . " 2>/dev/null");
                        exec("sudo chown -R www-data:www-data " . escapeshellarg($dir) . " 2>/dev/null");
                        exec("sudo chmod -R 755 " . escapeshellarg($dir) . " 2>/dev/null");
                    }
                }
                if (!is_dir($dir) || !is_writable($dir)) {
                    throw new \Exception("Local backup directory '{$dir}' is not writable.");
                }
                return ['success' => true, 'message' => "Local storage is writable and ready ({$dir})."];
            }

            if (in_array($driver, ['backblaze', 'r2', 'wasabi', 's3', 'custom_s3'])) {
                return $this->testS3Connection($driver, $creds);
            }

            if ($driver === 'google_drive') {
                return $this->testGoogleDriveConnection($creds);
            }

            throw new \Exception("Unsupported storage driver: {$driver}");

        } catch (\Throwable $e) {
            Log::warning("Backup destination connection test failed for '{$destination->name}': " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Upload a backup file to remote destination
     */
    public function uploadBackup(BackupRecord $record, BackupDestination $destination): array
    {
        $localFilePath = $record->file_path;

        if (!file_exists($localFilePath)) {
            return [
                'success' => false,
                'remote_path' => null,
                'error' => "Local backup file not found on disk at: {$localFilePath}",
            ];
        }

        try {
            $driver = $destination->driver;
            $creds = $destination->credentials ?: [];
            $fileName = $record->file_name;
            $targetFolder = $record->domain ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', strtolower($record->domain)) : 'server';
            $remoteKey = "nimbus-backups/{$targetFolder}/{$fileName}";

            if ($driver === 'local') {
                return [
                    'success' => true,
                    'remote_path' => $localFilePath,
                    'error' => null,
                ];
            }

            if (in_array($driver, ['backblaze', 'r2', 'wasabi', 's3', 'custom_s3'])) {
                $disk = $this->buildS3Disk($driver, $creds);
                $fileStream = fopen($localFilePath, 'r');
                if (!$fileStream) {
                    throw new \Exception("Could not open local file for reading: {$localFilePath}");
                }

                $uploaded = $disk->put($remoteKey, $fileStream);
                if (is_resource($fileStream)) {
                    fclose($fileStream);
                }

                if (!$uploaded) {
                    throw new \Exception("Failed to stream file to S3 destination.");
                }

                return [
                    'success' => true,
                    'remote_path' => $remoteKey,
                    'error' => null,
                ];
            }

            if ($driver === 'google_drive') {
                $fileId = $this->uploadToGoogleDrive($localFilePath, $fileName, $creds);
                return [
                    'success' => true,
                    'remote_path' => $fileId,
                    'error' => null,
                ];
            }

            throw new \Exception("Unsupported storage driver: {$driver}");

        } catch (\Throwable $e) {
            Log::error("Failed to upload backup '{$record->file_name}' to '{$destination->name}': " . $e->getMessage());
            return [
                'success' => false,
                'remote_path' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete remote file associated with a backup record
     */
    public function deleteRemoteFile(BackupRecord $record): bool
    {
        if (empty($record->remote_path) || $record->remote_status !== 'synced') {
            return false;
        }

        $destination = $record->destination;
        if (!$destination) {
            return false;
        }

        try {
            $driver = $destination->driver;
            $creds = $destination->credentials ?: [];

            if (in_array($driver, ['backblaze', 'r2', 'wasabi', 's3', 'custom_s3'])) {
                $disk = $this->buildS3Disk($driver, $creds);
                return $disk->delete($record->remote_path);
            }

            if ($driver === 'google_drive') {
                $accessToken = $this->getGoogleDriveAccessToken($creds);
                $response = Http::withToken($accessToken)->delete("https://www.googleapis.com/drive/v3/files/{$record->remote_path}");
                return $response->successful();
            }

            return false;
        } catch (\Throwable $e) {
            Log::warning("Failed to delete remote backup file: " . $e->getMessage());
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // S3 & S3-Compatible Storage Implementation
    // ─────────────────────────────────────────────────────────────

    /**
     * Build dynamic S3 flysystem disk for S3, Backblaze, R2, Wasabi, etc.
     */
    protected function buildS3Disk(string $driver, array $creds)
    {
        $bucket = $creds['bucket'] ?? '';
        $key = $creds['key'] ?? ($creds['key_id'] ?? ($creds['access_key'] ?? ''));
        $secret = $creds['secret'] ?? ($creds['application_key'] ?? ($creds['secret_key'] ?? ''));
        $region = $creds['region'] ?? 'us-east-1';
        $endpoint = $creds['endpoint'] ?? null;
        $usePathStyle = !empty($creds['use_path_style']);

        // Driver-specific defaults
        if ($driver === 'backblaze') {
            if (empty($endpoint)) {
                $endpoint = "https://s3.{$region}.backblazeb2.com";
            } elseif (!str_starts_with($endpoint, 'http')) {
                $endpoint = "https://{$endpoint}";
            }
            $usePathStyle = false;
        } elseif ($driver === 'r2') {
            $accountId = $creds['account_id'] ?? '';
            if (empty($endpoint) && !empty($accountId)) {
                $endpoint = "https://{$accountId}.r2.cloudflarestorage.com";
            }
            $region = 'auto';
            $usePathStyle = false;
        } elseif ($driver === 'wasabi') {
            if (empty($endpoint)) {
                $endpoint = "https://s3.{$region}.wasabisys.com";
            } elseif (!str_starts_with($endpoint, 'http')) {
                $endpoint = "https://{$endpoint}";
            }
            $usePathStyle = false;
        }

        if (empty($bucket)) {
            throw new \Exception("Bucket name is required.");
        }
        if (empty($key) || empty($secret)) {
            throw new \Exception("Access Key / Key ID and Secret / Application Key are required.");
        }

        $config = [
            'driver' => 's3',
            'key' => $key,
            'secret' => $secret,
            'region' => $region ?: 'us-east-1',
            'bucket' => $bucket,
            'throw' => true,
        ];

        if (!empty($endpoint)) {
            $config['endpoint'] = $endpoint;
        }

        if ($usePathStyle) {
            $config['use_path_style_endpoint'] = true;
        }

        return Storage::build($config);
    }

    /**
     * Test S3 connection by uploading and removing a small test sentinel file
     */
    protected function testS3Connection(string $driver, array $creds): array
    {
        $disk = $this->buildS3Disk($driver, $creds);
        $testKey = 'nimbus-backups/.test_connection_' . time() . '.txt';

        $disk->put($testKey, 'Nimbus Backup connection test at ' . now()->toDateTimeString());

        if (!$disk->exists($testKey)) {
            throw new \Exception("Write test failed: Unable to verify written test object.");
        }

        $disk->delete($testKey);

        $driverNames = [
            'backblaze' => 'Backblaze B2',
            'r2' => 'Cloudflare R2',
            'wasabi' => 'Wasabi Hot Cloud Storage',
            's3' => 'Amazon S3',
            'custom_s3' => 'S3-Compatible Storage',
        ];
        $label = $driverNames[$driver] ?? 'S3 Storage';

        return [
            'success' => true,
            'message' => "Successfully connected to {$label} (Bucket: {$creds['bucket']}). Read and write permissions verified!",
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Google Drive Implementation (Service Account & OAuth)
    // ─────────────────────────────────────────────────────────────

    /**
     * Test Google Drive connection
     */
    protected function testGoogleDriveConnection(array $creds): array
    {
        $accessToken = $this->getGoogleDriveAccessToken($creds);

        // Check Drive About API
        $response = Http::withToken($accessToken)
            ->timeout(15)
            ->get('https://www.googleapis.com/drive/v3/about?fields=user,storageQuota');

        if (!$response->successful()) {
            $errorMsg = $response->json('error.message') ?: "HTTP " . $response->status();
            throw new \Exception("Google Drive authentication failed: {$errorMsg}");
        }

        $data = $response->json();
        $userDisplayName = $data['user']['displayName'] ?? ($data['user']['emailAddress'] ?? 'Google User');

        // If folder ID specified, verify folder exists and is accessible
        $folderId = trim($creds['folder_id'] ?? '');
        if (!empty($folderId)) {
            $folderResponse = Http::withToken($accessToken)
                ->timeout(15)
                ->get("https://www.googleapis.com/drive/v3/files/{$folderId}?fields=id,name,mimeType");

            if (!$folderResponse->successful()) {
                throw new \Exception("Connected as {$userDisplayName}, but the specified Folder ID '{$folderId}' was not found or has no access.");
            }
            $folderName = $folderResponse->json('name') ?: $folderId;
            return [
                'success' => true,
                'message' => "Connected successfully to Google Drive ({$userDisplayName}). Target Folder: '{$folderName}' verified.",
            ];
        }

        return [
            'success' => true,
            'message' => "Connected successfully to Google Drive as {$userDisplayName} (Root directory).",
        ];
    }

    /**
     * Upload a file to Google Drive using multipart/resumable upload
     */
    protected function uploadToGoogleDrive(string $localFilePath, string $fileName, array $creds): string
    {
        $accessToken = $this->getGoogleDriveAccessToken($creds);
        $folderId = trim($creds['folder_id'] ?? '');

        $metadata = [
            'name' => $fileName,
        ];

        if (!empty($folderId)) {
            $metadata['parents'] = [$folderId];
        }

        // For large files (> 5MB), use resumable upload session
        $fileSize = filesize($localFilePath);
        $mimeType = 'application/gzip';
        if (str_ends_with($fileName, '.sql.gz')) {
            $mimeType = 'application/x-gzip';
        }

        // 1. Initiate Resumable Upload
        $initResponse = Http::withToken($accessToken)
            ->withHeaders([
                'X-Upload-Content-Type' => $mimeType,
                'X-Upload-Content-Length' => (string) $fileSize,
                'Content-Type' => 'application/json; charset=UTF-8',
            ])
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable', $metadata);

        if (!$initResponse->successful() || !$initResponse->header('Location')) {
            // Fallback to direct multipart if resumable initiation failed
            $fileContents = file_get_contents($localFilePath);
            $uploadResponse = Http::withToken($accessToken)
                ->attach('metadata', json_encode($metadata), 'metadata.json', ['Content-Type' => 'application/json'])
                ->attach('file', $fileContents, $fileName, ['Content-Type' => $mimeType])
                ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart');

            if (!$uploadResponse->successful()) {
                $err = $uploadResponse->json('error.message') ?: "HTTP " . $uploadResponse->status();
                throw new \Exception("Google Drive upload failed: {$err}");
            }
            return $uploadResponse->json('id');
        }

        $sessionUrl = $initResponse->header('Location');

        // 2. Stream/Put File to Resumable Session URL
        $handle = fopen($localFilePath, 'rb');
        $ch = curl_init($sessionUrl);
        curl_setopt($ch, CURLOPT_PUT, true);
        curl_setopt($ch, CURLOPT_INFILE, $handle);
        curl_setopt($ch, CURLOPT_INFILESIZE, $fileSize);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: {$mimeType}",
            "Content-Length: {$fileSize}",
        ]);

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        if (is_resource($handle)) {
            fclose($handle);
        }

        if ($curlError) {
            throw new \Exception("Google Drive upload cURL error: {$curlError}");
        }

        if ($httpCode !== 200 && $httpCode !== 201) {
            $decoded = json_decode($rawResponse, true);
            $err = $decoded['error']['message'] ?? "HTTP Code {$httpCode}: {$rawResponse}";
            throw new \Exception("Google Drive stream upload failed: {$err}");
        }

        $responseObj = json_decode($rawResponse, true);
        if (empty($responseObj['id'])) {
            throw new \Exception("Google Drive did not return a valid file ID.");
        }

        return $responseObj['id'];
    }

    /**
     * Obtain Google Drive Access Token via OAuth Refresh Token or Service Account JSON
     */
    protected function getGoogleDriveAccessToken(array $creds): string
    {
        $authType = $creds['auth_type'] ?? (empty($creds['service_account_json']) ? 'oauth' : 'service_account');

        if ($authType === 'service_account' && !empty($creds['service_account_json'])) {
            return $this->getServiceAccountAccessToken($creds['service_account_json']);
        }

        // OAuth 2.0 Flow
        $clientId = trim($creds['client_id'] ?? '');
        $clientSecret = trim($creds['client_secret'] ?? '');
        $refreshToken = trim($creds['refresh_token'] ?? '');

        if (empty($clientId) || empty($clientSecret) || empty($refreshToken)) {
            throw new \Exception("Google Drive OAuth requires Client ID, Client Secret, and Refresh Token (or Service Account JSON).");
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (!$response->successful()) {
            $err = $response->json('error_description') ?: ($response->json('error') ?: "HTTP " . $response->status());
            throw new \Exception("Failed to refresh Google Drive token: {$err}");
        }

        $token = $response->json('access_token');
        if (empty($token)) {
            throw new \Exception("Google OAuth endpoint did not return an access_token.");
        }

        return $token;
    }

    /**
     * Generate access token from Service Account JSON using JWT
     */
    protected function getServiceAccountAccessToken(string|array $serviceAccountJson): string
    {
        $data = is_array($serviceAccountJson) ? $serviceAccountJson : json_decode($serviceAccountJson, true);
        if (empty($data['client_email']) || empty($data['private_key'])) {
            throw new \Exception("Invalid Service Account JSON: Missing client_email or private_key.");
        }

        $now = time();
        $jwtHeader = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $jwtClaim = base64_encode(json_encode([
            'iss' => $data['client_email'],
            'scope' => 'https://www.googleapis.com/auth/drive.file https://www.googleapis.com/auth/drive',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ]));

        $signatureInput = $jwtHeader . '.' . $jwtClaim;
        $signature = '';
        $privateKey = openssl_pkey_get_private($data['private_key']);
        if (!$privateKey) {
            throw new \Exception("Invalid Service Account private key format.");
        }

        if (!openssl_sign($signatureInput, $signature, $privateKey, 'SHA256')) {
            throw new \Exception("Failed to sign Google Service Account JWT.");
        }

        $jwt = $signatureInput . '.' . base64_encode($signature);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (!$response->successful()) {
            $err = $response->json('error_description') ?: ($response->json('error') ?: "HTTP " . $response->status());
            throw new \Exception("Google Service Account auth failed: {$err}");
        }

        $token = $response->json('access_token');
        if (empty($token)) {
            throw new \Exception("Google Token endpoint returned empty access token.");
        }

        return $token;
    }
}
