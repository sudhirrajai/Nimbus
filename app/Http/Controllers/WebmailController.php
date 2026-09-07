<?php

namespace App\Http\Controllers;

use App\Services\WebmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class WebmailController extends Controller
{
    protected WebmailService $webmail;

    public function __construct(WebmailService $webmail)
    {
        $this->webmail = $webmail;
    }

    /**
     * Display the main Webmail interface
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $targetEmail = $request->query('account');

        // Check if an email account was specified from Nimbus panel
        if ($targetEmail && $user) {
            $parts = explode('@', $targetEmail);
            $domain = $parts[1] ?? '';
            if ($user->isRoot() || in_array($domain, $user->accessibleDomains())) {
                session(['webmail_email' => $targetEmail]);
            }
        }

        $activeEmail = session('webmail_email');

        // If no active webmail session but user is logged into Nimbus, auto-pick first mailbox
        if (!$activeEmail && $user) {
            $firstAccount = $this->getFirstAccessibleAccount($user);
            if ($firstAccount) {
                $activeEmail = $firstAccount;
                session(['webmail_email' => $activeEmail]);
            }
        }

        // If still no session, redirect to standalone webmail login
        if (!$activeEmail) {
            return redirect()->route('webmail.login');
        }

        // Get list of accessible accounts for quick-switcher
        $accessibleAccounts = [];
        if ($user) {
            $accessibleAccounts = $this->getAccessibleAccountsList($user);
        } else {
            $accessibleAccounts = [['email' => $activeEmail, 'name' => $activeEmail]];
        }

        // Fetch mailbox quota info if available
        $quota = ['used' => 0, 'limit' => 1024];
        try {
            $dbUser = DB::table('virtual_users')->where('email', $activeEmail)->first();
            if ($dbUser) {
                $quota['limit'] = $dbUser->quota ?? 1024;
            }
        } catch (\Exception $e) {}

        $folders = $this->webmail->getFolders($activeEmail);

        return Inertia::render('Webmail/Index', [
            'currentAccount' => [
                'email' => $activeEmail,
                'quota' => $quota
            ],
            'accounts' => $accessibleAccounts,
            'isNimbusUser' => $user !== null,
            'initialFolders' => $folders
        ]);
    }

    /**
     * Standalone Webmail login view
     */
    public function showLogin()
    {
        if (session('webmail_email')) {
            return redirect()->route('webmail.index');
        }

        return Inertia::render('Webmail/Login');
    }

    /**
     * Authenticate standalone mailbox user
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $email = strtolower(trim($request->input('email')));
        $password = $request->input('password');

        try {
            $account = DB::table('virtual_users')->where('email', $email)->first();

            if (!$account || !$account->active) {
                return back()->withErrors(['email' => 'Invalid email address or account is inactive.']);
            }

            // Verify password using Dovecot SHA512-CRYPT or Standard Hash
            if (!$this->verifyPassword($password, $account->password)) {
                return back()->withErrors(['password' => 'Incorrect password.']);
            }

            session(['webmail_email' => $email]);

            return redirect()->route('webmail.index');
        } catch (\Exception $e) {
            Log::error("Webmail login error: " . $e->getMessage());
            return back()->withErrors(['email' => 'Login failed due to a server error.']);
        }
    }

    /**
     * SSO Auto-login endpoint for Nimbus panel
     */
    public function ssoLogin(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $email = strtolower(trim($request->input('email')));

        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $parts = explode('@', $email);
        $domain = $parts[1] ?? '';

        if (!$user->isRoot() && !in_array($domain, $user->accessibleDomains())) {
            return response()->json(['error' => 'Permission denied for this email domain'], 403);
        }

        // Set session
        session(['webmail_email' => $email]);

        return response()->json([
            'success' => true,
            'url' => route('webmail.index', ['account' => $email])
        ]);
    }

    /**
     * Switch active email account
     */
    public function switchAccount(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $email = strtolower(trim($request->input('email')));
        $user = auth()->user();

        if ($user) {
            $parts = explode('@', $email);
            $domain = $parts[1] ?? '';
            if ($user->isRoot() || in_array($domain, $user->accessibleDomains())) {
                session(['webmail_email' => $email]);
                return response()->json(['success' => true]);
            }
        }

        return response()->json(['error' => 'Unauthorized account switch'], 403);
    }

    /**
     * Logout from Webmail session
     */
    public function logout(Request $request)
    {
        session()->forget('webmail_email');

        if (auth()->check()) {
            return redirect()->route('email.index');
        }

        return redirect()->route('webmail.login');
    }

    // ─────────────────────────────────────────────────────────────
    // Webmail AJAX / API Endpoints
    // ─────────────────────────────────────────────────────────────

    /**
     * Get mailbox folders with unread and total counts
     */
    public function getFolders()
    {
        $email = session('webmail_email');
        if (!$email) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $folders = $this->webmail->getFolders($email);
        return response()->json(['folders' => $folders]);
    }

    /**
     * Get list of messages in a folder
     */
    public function getMessages(Request $request)
    {
        $email = session('webmail_email');
        if (!$email) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $folder = $request->query('folder', 'INBOX');
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('perPage', 25);
        $search = $request->query('search');
        $filter = $request->query('filter');

        $result = $this->webmail->getMessages($email, $folder, $page, $perPage, $search, $filter);
        return response()->json($result);
    }

    /**
     * Get single full message
     */
    public function getMessage(Request $request, string $id)
    {
        $email = session('webmail_email');
        if (!$email) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $message = $this->webmail->getMessage($email, $id);
        if (!$message) {
            return response()->json(['error' => 'Message not found'], 404);
        }

        return response()->json(['message' => $message]);
    }

    /**
     * Send email via Postfix SMTP
     */
    public function sendMessage(Request $request)
    {
        $email = session('webmail_email');
        if (!$email) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'to' => 'required',
            'subject' => 'nullable|string',
            'bodyHtml' => 'nullable|string',
            'bodyText' => 'nullable|string'
        ]);

        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                if ($file->isValid()) {
                    $attachments[] = [
                        'path' => $file->getRealPath(),
                        'name' => $file->getClientOriginalName(),
                        'mime' => $file->getClientMimeType()
                    ];
                }
            }
        }

        $data = [
            'to' => $request->input('to'),
            'cc' => $request->input('cc'),
            'bcc' => $request->input('bcc'),
            'subject' => $request->input('subject', '(No Subject)'),
            'bodyHtml' => $request->input('bodyHtml'),
            'bodyText' => $request->input('bodyText')
        ];

        $res = $this->webmail->sendEmail($email, $data, $attachments);

        if ($res['success']) {
            return response()->json($res);
        }

        return response()->json($res, 500);
    }

    /**
     * Update flags (read/unread, starred)
     */
    public function updateFlags(Request $request)
    {
        $email = session('webmail_email');
        if (!$email) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'messageIds' => 'required|array',
            'flags' => 'required|array'
        ]);

        $messageIds = $request->input('messageIds');
        $flags = $request->input('flags');

        foreach ($messageIds as $id) {
            $this->webmail->setMessageFlags($email, $id, $flags);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Move message(s) to destination folder
     */
    public function moveMessages(Request $request)
    {
        $email = session('webmail_email');
        if (!$email) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'messageIds' => 'required|array',
            'targetFolder' => 'required|string'
        ]);

        $messageIds = $request->input('messageIds');
        $targetFolder = $request->input('targetFolder');

        foreach ($messageIds as $id) {
            $this->webmail->moveMessage($email, $id, $targetFolder);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Delete message(s)
     */
    public function deleteMessages(Request $request)
    {
        $email = session('webmail_email');
        if (!$email) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'messageIds' => 'required|array',
            'permanent' => 'nullable|boolean'
        ]);

        $messageIds = $request->input('messageIds');
        $permanent = (bool) $request->input('permanent', false);

        foreach ($messageIds as $id) {
            $this->webmail->deleteMessage($email, $id, $permanent);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Download attachment
     */
    public function downloadAttachment(Request $request, string $id, int $index)
    {
        $email = session('webmail_email');
        if (!$email) {
            abort(401);
        }

        $attachment = $this->webmail->getAttachment($email, $id, $index);
        if (!$attachment) {
            abort(404, 'Attachment not found');
        }

        $data = base64_decode($attachment['content']);
        $filename = $attachment['filename'] ?: 'attachment';
        $mime = $attachment['mime'] ?: 'application/octet-stream';

        return response($data, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . addslashes($filename) . '"',
            'Content-Length' => strlen($data)
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Helper Methods
    // ─────────────────────────────────────────────────────────────

    protected function getFirstAccessibleAccount($user): ?string
    {
        try {
            $query = DB::table('virtual_users')
                ->join('virtual_domains', 'virtual_users.domain_id', '=', 'virtual_domains.id')
                ->where('virtual_users.active', true);

            if (!$user->isRoot()) {
                $query->whereIn('virtual_domains.name', $user->accessibleDomains());
            }

            $first = $query->select('virtual_users.email')->first();
            return $first ? $first->email : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function getAccessibleAccountsList($user): array
    {
        try {
            $query = DB::table('virtual_users')
                ->join('virtual_domains', 'virtual_users.domain_id', '=', 'virtual_domains.id')
                ->where('virtual_users.active', true);

            if (!$user->isRoot()) {
                $query->whereIn('virtual_domains.name', $user->accessibleDomains());
            }

            return $query->select('virtual_users.email', 'virtual_users.quota')->get()->map(function ($item) {
                return [
                    'email' => $item->email,
                    'name' => $item->email,
                    'quota' => $item->quota
                ];
            })->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function verifyPassword(string $inputPassword, string $storedHash): bool
    {
        // Dovecot SHA512-CRYPT scheme
        if (str_starts_with($storedHash, '{SHA512-CRYPT}')) {
            $hashWithoutScheme = substr($storedHash, 14);
            return crypt($inputPassword, $hashWithoutScheme) === $hashWithoutScheme;
        }

        // Standard crypt / blowfish / argon
        if (str_starts_with($storedHash, '$')) {
            return crypt($inputPassword, $storedHash) === $storedHash;
        }

        // Plain text fallback
        return hash_equals($storedHash, $inputPassword);
    }
}
