<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;

class WebmailService
{
    protected string $baseMailDir = '/var/mail/vhosts';

    /**
     * Get the root directory for a mailbox
     */
    public function getMailboxPath(string $email): ?string
    {
        $parts = explode('@', strtolower(trim($email)));
        if (count($parts) !== 2) {
            return null;
        }

        [$user, $domain] = $parts;

        // Try standard Dovecot vhosts location
        $paths = [
            "{$this->baseMailDir}/{$domain}/{$user}",
            "/var/vmail/{$domain}/{$user}",
            storage_path("app/mail/{$domain}/{$user}") // Local test fallback
        ];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                return $path;
            }
        }

        // Check virtual_users table maildir column
        try {
            $record = DB::table('virtual_users')->where('email', $email)->first();
            if ($record && !empty($record->maildir)) {
                $customPath = $record->maildir;
                if (!str_starts_with($customPath, '/')) {
                    $customPath = "{$this->baseMailDir}/" . rtrim($customPath, '/');
                }
                if (is_dir($customPath)) {
                    return $customPath;
                }
            }
        } catch (\Exception $e) {
            // DB might not be initialized in test
        }

        // Return default path even if not yet created so we can initialize it if needed
        return "{$this->baseMailDir}/{$domain}/{$user}";
    }

    /**
     * List all folders with unread and total message counts
     */
    public function getFolders(string $email): array
    {
        $mailboxPath = $this->getMailboxPath($email);
        $standardFolders = [
            'INBOX' => ['name' => 'Inbox', 'icon' => 'inbox', 'id' => 'INBOX'],
            'Starred' => ['name' => 'Starred', 'icon' => 'star', 'id' => 'Starred'],
            'Sent' => ['name' => 'Sent', 'icon' => 'send', 'id' => 'Sent'],
            'Drafts' => ['name' => 'Drafts', 'icon' => 'drafts', 'id' => 'Drafts'],
            'Junk' => ['name' => 'Spam', 'icon' => 'report', 'id' => 'Junk'],
            'Trash' => ['name' => 'Trash', 'icon' => 'delete', 'id' => 'Trash'],
            'Archive' => ['name' => 'Archive', 'icon' => 'archive', 'id' => 'Archive'],
        ];

        $folders = [];

        if (!$mailboxPath || !is_dir($mailboxPath)) {
            // Return empty stats
            foreach ($standardFolders as $id => $info) {
                $folders[] = [
                    'id' => $id,
                    'name' => $info['name'],
                    'icon' => $info['icon'],
                    'total' => 0,
                    'unread' => 0
                ];
            }
            return $folders;
        }

        foreach ($standardFolders as $id => $info) {
            $folderPath = $this->getFolderPath($mailboxPath, $id);
            $counts = $this->countFolderMessages($folderPath, $id, $mailboxPath);

            $folders[] = [
                'id' => $id,
                'name' => $info['name'],
                'icon' => $info['icon'],
                'total' => $counts['total'],
                'unread' => $counts['unread']
            ];
        }

        return $folders;
    }

    /**
     * Map folder ID to physical folder path
     */
    protected function getFolderPath(string $mailboxPath, string $folderId): string
    {
        return match (strtoupper($folderId)) {
            'INBOX' => $mailboxPath,
            'SENT' => "{$mailboxPath}/.Sent",
            'DRAFTS' => "{$mailboxPath}/.Drafts",
            'JUNK', 'SPAM' => is_dir("{$mailboxPath}/.Junk") ? "{$mailboxPath}/.Junk" : "{$mailboxPath}/.Spam",
            'TRASH' => "{$mailboxPath}/.Trash",
            'ARCHIVE' => "{$mailboxPath}/.Archive",
            'STARRED' => $mailboxPath, // Special virtual folder
            default => "{$mailboxPath}/.{$folderId}"
        };
    }

    /**
     * Count messages in folder
     */
    protected function countFolderMessages(string $folderPath, string $folderId, string $mailboxPath): array
    {
        if ($folderId === 'Starred') {
            // Count starred messages across all non-trash folders
            $starredCount = 0;
            $unreadStarred = 0;
            $foldersToScan = [$mailboxPath, "{$mailboxPath}/.Sent", "{$mailboxPath}/.Archive"];

            foreach ($foldersToScan as $fPath) {
                if (!is_dir($fPath)) continue;
                foreach (['cur', 'new'] as $sub) {
                    $dir = "{$fPath}/{$sub}";
                    if (!is_dir($dir)) continue;
                    $files = scandir($dir);
                    foreach ($files as $file) {
                        if ($file === '.' || $file === '..') continue;
                        if (str_contains($file, ':2,') && str_contains(explode(':2,', $file)[1] ?? '', 'F')) {
                            $starredCount++;
                            if (!str_contains(explode(':2,', $file)[1] ?? '', 'S')) {
                                $unreadStarred++;
                            }
                        }
                    }
                }
            }
            return ['total' => $starredCount, 'unread' => $unreadStarred];
        }

        if (!is_dir($folderPath)) {
            return ['total' => 0, 'unread' => 0];
        }

        $total = 0;
        $unread = 0;

        // In Maildir: 'new' is always unread; 'cur' contains messages with flags
        $newDir = "{$folderPath}/new";
        if (is_dir($newDir)) {
            $newFiles = scandir($newDir);
            foreach ($newFiles as $file) {
                if ($file !== '.' && $file !== '..') {
                    $total++;
                    $unread++;
                }
            }
        }

        $curDir = "{$folderPath}/cur";
        if (is_dir($curDir)) {
            $curFiles = scandir($curDir);
            foreach ($curFiles as $file) {
                if ($file !== '.' && $file !== '..') {
                    $total++;
                    $flags = '';
                    if (str_contains($file, ':2,')) {
                        $flags = explode(':2,', $file)[1] ?? '';
                    }
                    if (!str_contains($flags, 'S')) {
                        $unread++;
                    }
                }
            }
        }

        return ['total' => $total, 'unread' => $unread];
    }

    /**
     * Get paginated messages in a folder
     */
    public function getMessages(string $email, string $folder = 'INBOX', int $page = 1, int $perPage = 25, ?string $search = null, ?string $filter = null): array
    {
        $mailboxPath = $this->getMailboxPath($email);
        if (!$mailboxPath || !is_dir($mailboxPath)) {
            return [
                'messages' => [],
                'total' => 0,
                'page' => $page,
                'perPage' => $perPage,
                'lastPage' => 1
            ];
        }

        $isStarredVirtual = (strtoupper($folder) === 'STARRED');
        $folderPath = $this->getFolderPath($mailboxPath, $folder);

        $messageFiles = [];

        if ($isStarredVirtual) {
            $foldersToScan = [$mailboxPath, "{$mailboxPath}/.Sent", "{$mailboxPath}/.Archive"];
            foreach ($foldersToScan as $scanPath) {
                if (!is_dir($scanPath)) continue;
                $this->collectFolderFiles($scanPath, $messageFiles, true);
            }
        } else {
            if (is_dir($folderPath)) {
                $this->collectFolderFiles($folderPath, $messageFiles);
            }
        }

        // Sort by file modification time or parsed timestamp (newest first)
        usort($messageFiles, function ($a, $b) {
            return $b['mtime'] <=> $a['mtime'];
        });

        // Parse summaries for filtering / search / response
        $parsedList = [];
        foreach ($messageFiles as $item) {
            $summary = $this->parseMessageHeaderSummary($item['filepath'], $item['folder'], $item['filename']);
            if (!$summary) continue;

            // Filter logic
            if ($filter === 'unread' && $summary['isRead']) continue;
            if ($filter === 'starred' && !$summary['isStarred']) continue;
            if ($filter === 'attachments' && !$summary['hasAttachments']) continue;

            // Search logic
            if (!empty($search)) {
                $q = mb_strtolower($search);
                $match = str_contains(mb_strtolower($summary['subject']), $q) ||
                         str_contains(mb_strtolower($summary['from']['name'] ?? ''), $q) ||
                         str_contains(mb_strtolower($summary['from']['address'] ?? ''), $q) ||
                         str_contains(mb_strtolower($summary['preview'] ?? ''), $q);
                if (!$match) continue;
            }

            $parsedList[] = $summary;
        }

        $total = count($parsedList);
        $offset = ($page - 1) * $perPage;
        $paginated = array_slice($parsedList, $offset, $perPage);

        return [
            'messages' => $paginated,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => max(1, (int) ceil($total / $perPage))
        ];
    }

    /**
     * Helper to collect files from cur and new
     */
    protected function collectFolderFiles(string $dir, array &$collection, bool $starredOnly = false): void
    {
        $folderName = basename($dir);
        if (str_starts_with($folderName, '.')) {
            $folderName = substr($folderName, 1);
        } else {
            $folderName = 'INBOX';
        }

        foreach (['new', 'cur'] as $sub) {
            $subPath = "{$dir}/{$sub}";
            if (!is_dir($subPath)) continue;
            $files = scandir($subPath);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                $filePath = "{$subPath}/{$file}";

                $flags = '';
                if (str_contains($file, ':2,')) {
                    $flags = explode(':2,', $file)[1] ?? '';
                }

                $isStarred = str_contains($flags, 'F');
                if ($starredOnly && !$isStarred) continue;

                $mtime = filemtime($filePath);

                $collection[] = [
                    'filepath' => $filePath,
                    'filename' => $file,
                    'folder' => $folderName,
                    'sub' => $sub,
                    'flags' => $flags,
                    'mtime' => $mtime
                ];
            }
        }
    }

    /**
     * Fast header parser for email summary list
     */
    protected function parseMessageHeaderSummary(string $filePath, string $folder, string $filename): ?array
    {
        if (!file_exists($filePath)) return null;

        // Read first 8KB of message for headers & quick snippet
        $handle = fopen($filePath, 'r');
        if (!$handle) return null;

        $rawHeaders = '';
        $inHeaders = true;
        $bodyPreviewRaw = '';

        while (($line = fgets($handle)) !== false) {
            if ($inHeaders) {
                if (rtrim($line, "\r\n") === '') {
                    $inHeaders = false;
                    continue;
                }
                $rawHeaders .= $line;
            } else {
                if (strlen($bodyPreviewRaw) < 500) {
                    $bodyPreviewRaw .= $line;
                } else {
                    break;
                }
            }
        }
        fclose($handle);

        $headers = $this->parseRawHeaders($rawHeaders);

        $subject = $this->decodeMimeHeader($headers['subject'] ?? '(No Subject)');
        $from = $this->parseAddressHeader($headers['from'] ?? '');
        $to = $this->parseAddressList($headers['to'] ?? '');
        $dateStr = $headers['date'] ?? '';
        $timestamp = !empty($dateStr) ? @strtotime($dateStr) : filemtime($filePath);
        if (!$timestamp) $timestamp = filemtime($filePath);

        $contentType = $headers['content-type'] ?? 'text/plain';
        $hasAttachments = str_contains(strtolower($contentType), 'multipart/mixed') ||
                          str_contains(strtolower($contentType), 'attachment');

        $flags = '';
        if (str_contains($filename, ':2,')) {
            $flags = explode(':2,', $filename)[1] ?? '';
        }

        $isRead = str_contains($flags, 'S') || (str_contains($filePath, '/cur/') && str_contains($flags, 'S'));
        $isStarred = str_contains($flags, 'F');
        $isDraft = str_contains($flags, 'D');

        // Clean snippet by stripping MIME boundaries and sub-headers
        $snippet = $this->cleanSnippetText($bodyPreviewRaw);

        // Unique URL-safe ID
        $id = $this->encodeId("{$folder}::{$filename}");

        return [
            'id' => $id,
            'folder' => $folder,
            'filename' => $filename,
            'subject' => $subject,
            'from' => $from,
            'to' => $to,
            'date' => date('c', $timestamp),
            'dateFormatted' => $this->formatEmailDate($timestamp),
            'timestamp' => $timestamp,
            'isRead' => $isRead,
            'isStarred' => $isStarred,
            'isDraft' => $isDraft,
            'hasAttachments' => $hasAttachments,
            'preview' => $snippet,
            'size' => filesize($filePath)
        ];
    }

    /**
     * Get full detailed message including HTML, text, and attachments
     */
    public function getMessage(string $email, string $messageId): ?array
    {
        $info = $this->resolveMessageFile($email, $messageId);
        if (!$info || !file_exists($info['filepath'])) {
            return null;
        }

        $rawContent = file_get_contents($info['filepath']);
        if ($rawContent === false) {
            return null;
        }

        $parsed = $this->parseFullMimeMessage($rawContent);

        // Auto mark as read if not already
        $this->setMessageFlags($email, $messageId, ['read' => true]);

        $flags = '';
        if (str_contains($info['filename'], ':2,')) {
            $flags = explode(':2,', $info['filename'])[1] ?? '';
        }

        $timestamp = !empty($parsed['headers']['date'] ?? null)
            ? @strtotime($parsed['headers']['date'])
            : filemtime($info['filepath']);
        if (!$timestamp) $timestamp = filemtime($info['filepath']);

        return [
            'id' => $messageId,
            'folder' => $info['folder'],
            'subject' => $this->decodeMimeHeader($parsed['headers']['subject'] ?? '(No Subject)'),
            'from' => $this->parseAddressHeader($parsed['headers']['from'] ?? ''),
            'to' => $this->parseAddressList($parsed['headers']['to'] ?? ''),
            'cc' => $this->parseAddressList($parsed['headers']['cc'] ?? ''),
            'bcc' => $this->parseAddressList($parsed['headers']['bcc'] ?? ''),
            'replyTo' => $this->parseAddressHeader($parsed['headers']['reply-to'] ?? ''),
            'date' => date('c', $timestamp),
            'dateFormatted' => date('M j, Y, g:i a', $timestamp),
            'timestamp' => $timestamp,
            'messageId' => $parsed['headers']['message-id'] ?? '',
            'inReplyTo' => $parsed['headers']['in-reply-to'] ?? '',
            'bodyHtml' => $parsed['bodyHtml'],
            'bodyText' => $parsed['bodyText'],
            'attachments' => $parsed['attachments'],
            'isRead' => true,
            'isStarred' => str_contains($flags, 'F'),
            'rawHeaders' => $parsed['rawHeaders']
        ];
    }

    /**
     * Send email via Postfix SMTP and save copy to .Sent
     */
    public function sendEmail(string $fromEmail, array $data, array $attachments = []): array
    {
        try {
            $to = is_array($data['to']) ? $data['to'] : array_filter(array_map('trim', explode(',', $data['to'])));
            $cc = !empty($data['cc']) ? (is_array($data['cc']) ? $data['cc'] : array_filter(array_map('trim', explode(',', $data['cc'])))) : [];
            $bcc = !empty($data['bcc']) ? (is_array($data['bcc']) ? $data['bcc'] : array_filter(array_map('trim', explode(',', $data['bcc'])))) : [];
            $subject = trim($data['subject'] ?? '(No Subject)');
            $bodyHtml = $data['bodyHtml'] ?? '';
            $bodyText = $data['bodyText'] ?? strip_tags($bodyHtml);

            if (empty($to)) {
                return ['success' => false, 'error' => 'Recipient (To) address is required.'];
            }

            // Create Symfony Email
            $email = (new Email())
                ->from($fromEmail)
                ->to(...$to)
                ->subject($subject);

            if (!empty($cc)) $email->cc(...$cc);
            if (!empty($bcc)) $email->bcc(...$bcc);

            if (!empty($bodyHtml)) {
                $email->html($bodyHtml);
                if (!empty($bodyText)) {
                    $email->text($bodyText);
                }
            } else {
                $email->text($bodyText);
            }

            // Add attachments
            foreach ($attachments as $att) {
                if (isset($att['path']) && file_exists($att['path'])) {
                    $email->attachFromPath(
                        $att['path'],
                        $att['name'] ?? basename($att['path']),
                        $att['mime'] ?? null
                    );
                } elseif (isset($att['content']) && isset($att['name'])) {
                    $email->attach(
                        $att['content'],
                        $att['name'],
                        $att['mime'] ?? 'application/octet-stream'
                    );
                }
            }

            $allRecipients = array_merge($to, $cc, $bcc);
            $sent = false;

            // Strategy 1: Local EsmtpTransport with AutoTLS disabled (prevents peer certificate CN mismatch on localhost)
            try {
                $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport('127.0.0.1', 25, false);
                $transport->setAutoTls(false);
                $mailer = new Mailer($transport);
                $mailer->send($email);
                $sent = true;
            } catch (\Exception $eTransport) {
                Log::warning("EsmtpTransport failed, falling back to raw SMTP: " . $eTransport->getMessage());
            }

            // Strategy 2: Direct raw socket SMTP to Postfix on 127.0.0.1:25
            if (!$sent) {
                try {
                    $this->sendRawSmtp('127.0.0.1', 25, $fromEmail, $allRecipients, $email->toString());
                    $sent = true;
                } catch (\Exception $eRaw) {
                    Log::warning("Raw socket SMTP failed, falling back to sendmail binary: " . $eRaw->getMessage());
                }
            }

            // Strategy 3: System sendmail fallback
            if (!$sent) {
                try {
                    $transport = Transport::fromDsn('sendmail://default');
                    $mailer = new Mailer($transport);
                    $mailer->send($email);
                    $sent = true;
                } catch (\Exception $eSendmail) {
                    throw new \Exception("All delivery methods failed. SMTP error: " . $eSendmail->getMessage());
                }
            }

            // Save copy into .Sent folder in Maildir safely (permission issues should not fail sending)
            try {
                $this->saveMessageToFolder($fromEmail, 'Sent', $email->toString(), ['seen' => true]);
            } catch (\Throwable $eSent) {
                Log::warning("Webmail: Could not save copy to .Sent folder: " . $eSent->getMessage());
            }

            return [
                'success' => true,
                'message' => 'Email sent successfully.'
            ];
        } catch (\Exception $e) {
            Log::error("WebmailService sendEmail error for {$fromEmail}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to send email: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Save message as a draft in .Drafts folder
     */
    public function saveDraft(string $fromEmail, array $data, ?string $existingDraftId = null): array
    {
        try {
            $email = new Email();
            $email->from(new Address($fromEmail));

            $toAddresses = $this->parseInputAddresses($data['to'] ?? '');
            if (!empty($toAddresses)) {
                $email->to(...$toAddresses);
            }

            if (!empty($data['cc'])) {
                $ccAddresses = $this->parseInputAddresses($data['cc']);
                if (!empty($ccAddresses)) $email->cc(...$ccAddresses);
            }

            if (!empty($data['bcc'])) {
                $bccAddresses = $this->parseInputAddresses($data['bcc']);
                if (!empty($bccAddresses)) $email->bcc(...$bccAddresses);
            }

            $email->subject($data['subject'] ?? '(No Subject)');

            if (!empty($data['bodyHtml'])) {
                $email->html($data['bodyHtml']);
            }
            if (!empty($data['bodyText'])) {
                $email->text($data['bodyText']);
            }

            // If updating an existing draft, delete the old file
            if ($existingDraftId) {
                $this->deleteMessage($fromEmail, $existingDraftId, true);
            }

            // Save to .Drafts folder with 'seen' and 'draft' flags
            $saved = $this->saveMessageToFolder($fromEmail, 'Drafts', $email->toString(), ['seen' => true, 'draft' => true]);

            if ($saved) {
                return [
                    'success' => true,
                    'message' => 'Draft saved successfully.'
                ];
            }

            return [
                'success' => false,
                'error' => 'Could not save draft.'
            ];
        } catch (\Throwable $e) {
            Log::error("WebmailService saveDraft error for {$fromEmail}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to save draft: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Save raw email message into specific folder (e.g. Sent, Drafts)
     */
    public function saveMessageToFolder(string $email, string $folder, string $rawMessage, array $flags = []): bool
    {
        try {
            $mailboxPath = $this->getMailboxPath($email);
            if (!$mailboxPath) return false;

            $targetDir = $this->getFolderPath($mailboxPath, $folder);
            $curDir = "{$targetDir}/cur";
            $newDir = "{$targetDir}/new";
            $tmpDir = "{$targetDir}/tmp";

            // Create directories if missing
            foreach ([$targetDir, $curDir, $newDir, $tmpDir] as $d) {
                if (!is_dir($d)) {
                    @mkdir($d, 0775, true);
                    @chmod($d, 0775);
                }
            }

            $uniq = time() . '.M' . rand(100000, 999999) . 'P' . getmypid() . '.' . gethostname();
            $flagStr = '';
            if (!empty($flags['seen'])) $flagStr .= 'S';
            if (!empty($flags['flagged'])) $flagStr .= 'F';
            if (!empty($flags['draft'])) $flagStr .= 'D';

            $destSub = !empty($flags['seen']) ? 'cur' : 'new';
            $destFilename = !empty($flagStr) ? "{$uniq}:2,{$flagStr}" : $uniq;

            $tmpFile = "{$tmpDir}/{$uniq}";
            $destFile = "{$targetDir}/{$destSub}/{$destFilename}";

            // Write with fallback if direct write fails due to permissions
            if (@file_put_contents($tmpFile, $rawMessage) === false) {
                // Ensure directory permissions via sudo
                @exec("sudo mkdir -p " . escapeshellarg($tmpDir) . " " . escapeshellarg("{$targetDir}/{$destSub}") . " && sudo chmod -R 775 " . escapeshellarg($targetDir));
                if (@file_put_contents($tmpFile, $rawMessage) === false) {
                    $escapedTmp = escapeshellarg($tmpFile);
                    $process = proc_open("sudo tee {$escapedTmp} > /dev/null", [
                        0 => ['pipe', 'r'],
                        1 => ['pipe', 'w'],
                        2 => ['pipe', 'w']
                    ], $pipes);
                    if (is_resource($process)) {
                        fwrite($pipes[0], $rawMessage);
                        fclose($pipes[0]);
                        fclose($pipes[1]);
                        fclose($pipes[2]);
                        proc_close($process);
                    }
                }
            }

            if (file_exists($tmpFile)) {
                @chmod($tmpFile, 0664);
                if (!@rename($tmpFile, $destFile)) {
                    @exec("sudo mv " . escapeshellarg($tmpFile) . " " . escapeshellarg($destFile) . " && sudo chown vmail:vmail " . escapeshellarg($destFile));
                } else {
                    @exec("sudo chown vmail:vmail " . escapeshellarg($destFile));
                }
                return true;
            }
        } catch (\Throwable $e) {
            Log::warning("WebmailService saveMessageToFolder error: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Set or toggle message flags (read/unread, star/unstar)
     */
    public function setMessageFlags(string $email, string $messageId, array $flags): bool
    {
        $info = $this->resolveMessageFile($email, $messageId);
        if (!$info || !file_exists($info['filepath'])) {
            return false;
        }

        $oldPath = $info['filepath'];
        $filename = $info['filename'];
        $folderDir = dirname(dirname($oldPath)); // parent of cur/new

        $baseName = explode(':2,', $filename)[0];
        $currentFlags = str_contains($filename, ':2,') ? (explode(':2,', $filename)[1] ?? '') : '';

        // Calculate new flags
        $flagSet = array_unique(str_split($currentFlags));

        if (isset($flags['read'])) {
            if ($flags['read'] && !in_array('S', $flagSet)) {
                $flagSet[] = 'S';
            } elseif (!$flags['read'] && in_array('S', $flagSet)) {
                $flagSet = array_diff($flagSet, ['S']);
            }
        }

        if (isset($flags['starred'])) {
            if ($flags['starred'] && !in_array('F', $flagSet)) {
                $flagSet[] = 'F';
            } elseif (!$flags['starred'] && in_array('F', $flagSet)) {
                $flagSet = array_diff($flagSet, ['F']);
            }
        }

        sort($flagSet);
        $newFlagStr = implode('', $flagSet);
        $newFilename = !empty($newFlagStr) ? "{$baseName}:2,{$newFlagStr}" : "{$baseName}:2,";

        // If read, move to cur, otherwise can stay or move
        $targetSub = in_array('S', $flagSet) ? 'cur' : 'cur';
        $newPath = "{$folderDir}/{$targetSub}/{$newFilename}";

        if ($oldPath !== $newPath) {
            if (!@rename($oldPath, $newPath)) {
                @exec("sudo mv " . escapeshellarg($oldPath) . " " . escapeshellarg($newPath) . " && sudo chown vmail:vmail " . escapeshellarg($newPath));
            }
        }

        return true;
    }

    /**
     * Move message to a destination folder
     */
    public function moveMessage(string $email, string $messageId, string $targetFolder): bool
    {
        $info = $this->resolveMessageFile($email, $messageId);
        if (!$info || !file_exists($info['filepath'])) {
            return false;
        }

        $mailboxPath = $this->getMailboxPath($email);
        $destFolderPath = $this->getFolderPath($mailboxPath, $targetFolder);

        foreach (['cur', 'new', 'tmp'] as $sub) {
            $d = "{$destFolderPath}/{$sub}";
            if (!is_dir($d)) {
                @mkdir($d, 0775, true);
                @exec("sudo mkdir -p " . escapeshellarg($d) . " && sudo chmod 775 " . escapeshellarg($d));
            }
        }

        $currentSub = str_contains($info['filepath'], '/new/') ? 'new' : 'cur';
        $destPath = "{$destFolderPath}/{$currentSub}/{$info['filename']}";

        if (!@rename($info['filepath'], $destPath)) {
            @exec("sudo mv " . escapeshellarg($info['filepath']) . " " . escapeshellarg($destPath) . " && sudo chown vmail:vmail " . escapeshellarg($destPath));
        }

        return true;
    }

    /**
     * Delete message (move to Trash, or permanent delete if already in Trash)
     */
    public function deleteMessage(string $email, string $messageId, bool $permanent = false): bool
    {
        $info = $this->resolveMessageFile($email, $messageId);
        if (!$info || !file_exists($info['filepath'])) {
            return false;
        }

        if ($permanent || strtoupper($info['folder']) === 'TRASH') {
            if (!@unlink($info['filepath'])) {
                @exec("sudo rm -f " . escapeshellarg($info['filepath']));
            }
            return true;
        }

        // Move to Trash
        return $this->moveMessage($email, $messageId, 'Trash');
    }

    /**
     * Encode string to URL-safe base64 ID
     */
    public function encodeId(string $str): string
    {
        return rtrim(strtr(base64_encode($str), '+/', '-_'), '=');
    }

    /**
     * Decode URL-safe or standard base64 ID
     */
    public function decodeId(string $str): string
    {
        $remainder = strlen($str) % 4;
        if ($remainder) {
            $str .= str_repeat('=', 4 - $remainder);
        }
        $decoded = @base64_decode(strtr($str, '-_', '+/'));
        if (!$decoded) {
            $decoded = @base64_decode($str);
        }
        return $decoded ?: '';
    }

    /**
     * Resolve message ID to physical filepath and folder
     */
    protected function resolveMessageFile(string $email, string $messageId): ?array
    {
        $decoded = $this->decodeId($messageId);
        if (!$decoded || !str_contains($decoded, '::')) {
            return null;
        }

        [$folder, $filename] = explode('::', $decoded, 2);
        $mailboxPath = $this->getMailboxPath($email);
        if (!$mailboxPath) return null;

        $folderPath = $this->getFolderPath($mailboxPath, $folder);

        // Check cur and new
        foreach (['cur', 'new'] as $sub) {
            $directPath = "{$folderPath}/{$sub}/{$filename}";
            if (file_exists($directPath)) {
                return [
                    'filepath' => $directPath,
                    'folder' => $folder,
                    'filename' => $filename,
                    'sub' => $sub
                ];
            }

            // Flag might have changed in filename, match by base prefix
            $baseName = explode(':2,', $filename)[0];
            if (is_dir("{$folderPath}/{$sub}")) {
                $files = scandir("{$folderPath}/{$sub}");
                foreach ($files as $f) {
                    if ($f === '.' || $f === '..') continue;
                    if (str_starts_with($f, $baseName)) {
                        return [
                            'filepath' => "{$folderPath}/{$sub}/{$f}",
                            'folder' => $folder,
                            'filename' => $f,
                            'sub' => $sub
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Direct raw socket SMTP to localhost Postfix (127.0.0.1:25)
     */
    protected function sendRawSmtp(string $host, int $port, string $from, array $recipients, string $rawMessage): bool
    {
        $socket = @fsockopen($host, $port, $errno, $errstr, 5);
        if (!$socket) {
            throw new \Exception("Could not connect to SMTP server {$host}:{$port} - {$errstr}");
        }

        $read = fgets($socket, 512);
        if (!str_starts_with($read, '220')) {
            fclose($socket);
            throw new \Exception("SMTP greeting failed: {$read}");
        }

        fputs($socket, "HELO localhost\r\n");
        $read = fgets($socket, 512);

        fputs($socket, "MAIL FROM:<{$from}>\r\n");
        $read = fgets($socket, 512);
        if (!str_starts_with($read, '250')) {
            fclose($socket);
            throw new \Exception("MAIL FROM rejected: {$read}");
        }

        foreach ($recipients as $rcpt) {
            $cleanRcpt = trim($rcpt);
            if (empty($cleanRcpt)) continue;
            fputs($socket, "RCPT TO:<{$cleanRcpt}>\r\n");
            $read = fgets($socket, 512);
            if (!str_starts_with($read, '250')) {
                fclose($socket);
                throw new \Exception("RCPT TO rejected for {$cleanRcpt}: {$read}");
            }
        }

        fputs($socket, "DATA\r\n");
        $read = fgets($socket, 512);
        if (!str_starts_with($read, '354')) {
            fclose($socket);
            throw new \Exception("DATA rejected: {$read}");
        }

        // Normalize line endings and escape leading dots
        $lines = explode("\n", str_replace("\r\n", "\n", $rawMessage));
        foreach ($lines as $line) {
            if (str_starts_with($line, '.')) {
                $line = '.' . $line;
            }
            fputs($socket, $line . "\r\n");
        }

        fputs($socket, ".\r\n");
        $read = fgets($socket, 512);
        if (!str_starts_with($read, '250')) {
            fclose($socket);
            throw new \Exception("Message body submission rejected: {$read}");
        }

        fputs($socket, "QUIT\r\n");
        fclose($socket);
        return true;
    }

    /**
     * Get attachment stream/download from message
     */
    public function getAttachment(string $email, string $messageId, int $attachmentIndex): ?array
    {
        $info = $this->resolveMessageFile($email, $messageId);
        if (!$info || !file_exists($info['filepath'])) {
            return null;
        }

        $rawContent = file_get_contents($info['filepath']);
        $parsed = $this->parseFullMimeMessage($rawContent);

        if (isset($parsed['attachments'][$attachmentIndex])) {
            return $parsed['attachments'][$attachmentIndex];
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // MIME Parsing Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Parse raw headers string into key-value map
     */
    protected function parseRawHeaders(string $rawHeaders): array
    {
        $headers = [];
        $lines = explode("\n", str_replace("\r", "", $rawHeaders));
        $currentKey = '';

        foreach ($lines as $line) {
            if (preg_match('/^([a-zA-Z0-9_-]+):\s*(.*)$/', $line, $matches)) {
                $currentKey = strtolower($matches[1]);
                $headers[$currentKey] = trim($matches[2]);
            } elseif (!empty($currentKey) && (str_starts_with($line, " ") || str_starts_with($line, "\t"))) {
                // Continuation line
                $headers[$currentKey] .= ' ' . trim($line);
            }
        }

        return $headers;
    }

    /**
     * Parse single email address header "Name <email@domain>"
     */
    protected function parseAddressHeader(string $raw): array
    {
        $raw = trim($raw);
        if (empty($raw)) return ['name' => '', 'address' => ''];

        if (preg_match('/^(.*?)\s*<([^>]+)>$/', $raw, $m)) {
            $name = trim($this->decodeMimeHeader($m[1]), '"\' ');
            return [
                'name' => !empty($name) ? $name : $m[2],
                'address' => trim($m[2])
            ];
        }

        return [
            'name' => $this->decodeMimeHeader($raw),
            'address' => trim($raw)
        ];
    }

    /**
     * Parse list of email addresses
     */
    protected function parseAddressList(string $raw): array
    {
        $raw = trim($raw);
        if (empty($raw)) return [];

        $addresses = [];
        $parts = explode(',', $raw);

        foreach ($parts as $part) {
            $item = $this->parseAddressHeader($part);
            if (!empty($item['address'])) {
                $addresses[] = $item;
            }
        }

        return $addresses;
    }

    /**
     * Decode RFC 2047 encoded-word headers (=?UTF-8?B?...?= or =?ISO-8859-1?Q?...?=)
     */
    public function decodeMimeHeader(string $text): string
    {
        if (function_exists('mb_decode_mimeheader')) {
            return mb_decode_mimeheader($text);
        }

        if (function_exists('iconv_mime_decode')) {
            return iconv_mime_decode($text, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        }

        return $text;
    }

    /**
     * Format timestamp to friendly email date (e.g. "10:30 AM", "Yesterday", "Oct 12")
     */
    protected function formatEmailDate(int $timestamp): string
    {
        $today = strtotime('today');
        $yesterday = strtotime('yesterday');

        if ($timestamp >= $today) {
            return date('g:i A', $timestamp);
        } elseif ($timestamp >= $yesterday) {
            return 'Yesterday';
        } elseif (date('Y', $timestamp) === date('Y')) {
            return date('M j', $timestamp);
        }

        return date('m/d/Y', $timestamp);
    }

    /**
     * Clean and extract readable text snippet from raw email body preview
     */
    protected function cleanSnippetText(string $raw): string
    {
        $lines = explode("\n", str_replace("\r", "", $raw));
        $cleanedLines = [];
        $skippingHeaders = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) continue;

            // Skip boundary lines (e.g. --1m-tEDKI or --===============...)
            if (str_starts_with($trimmed, '--')) {
                $skippingHeaders = true;
                continue;
            }

            // Skip sub-part MIME headers
            if ($skippingHeaders) {
                if (preg_match('/^(content-type|content-transfer-encoding|content-disposition|content-id):/i', $trimmed)) {
                    continue;
                }
                // Once we encounter a non-header line, stop skipping
                $skippingHeaders = false;
            }

            // Skip leftover MIME headers if any
            if (preg_match('/^(content-type|content-transfer-encoding|content-disposition|content-id):/i', $trimmed)) {
                continue;
            }

            // Decode quoted printable if detected
            if (str_contains($trimmed, '=')) {
                $trimmed = quoted_printable_decode($trimmed);
            }

            $cleanedLines[] = $trimmed;
        }

        $snippet = implode(' ', $cleanedLines);
        $snippet = strip_tags($snippet);
        $snippet = html_entity_decode($snippet, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $snippet = preg_replace('/\s+/', ' ', $snippet);
        return mb_substr(trim($snippet), 0, 140);
    }

    /**
     * Comprehensive MIME message parser
     */
    protected function parseFullMimeMessage(string $raw): array
    {
        // Split header and body
        $parts = explode("\r\n\r\n", $raw, 2);
        if (count($parts) < 2) {
            $parts = explode("\n\n", $raw, 2);
        }

        $rawHeaders = $parts[0] ?? '';
        $rawBody = $parts[1] ?? '';

        $headers = $this->parseRawHeaders($rawHeaders);
        $contentType = $headers['content-type'] ?? 'text/plain; charset=utf-8';
        $transferEncoding = strtolower($headers['content-transfer-encoding'] ?? '');

        $bodyHtml = '';
        $bodyText = '';
        $attachments = [];

        // Check if multipart
        if (preg_match('/multipart\/[a-z]+;\s*boundary=(?:"([^"]+)"|([^\s;]+))/i', $contentType, $matches)) {
            $boundary = $matches[1] ?: $matches[2];
            $this->parseMultipartBody($rawBody, $boundary, $bodyHtml, $bodyText, $attachments);
        } else {
            // Single part
            $decoded = $this->decodeContentTransfer($rawBody, $transferEncoding);
            $charset = $this->extractCharset($contentType);
            if ($charset && strtoupper($charset) !== 'UTF-8') {
                $decoded = @mb_convert_encoding($decoded, 'UTF-8', $charset);
            }

            if (str_contains(strtolower($contentType), 'text/html')) {
                $bodyHtml = $decoded;
            } else {
                $bodyText = $decoded;
            }
        }

        // Sanitize HTML body for security
        $cleanHtml = $this->sanitizeHtml($bodyHtml ?: nl2br(htmlspecialchars($bodyText)));

        return [
            'headers' => $headers,
            'rawHeaders' => $rawHeaders,
            'bodyHtml' => $cleanHtml,
            'bodyText' => $bodyText,
            'attachments' => $attachments
        ];
    }

    /**
     * Parse recursive multipart MIME body
     */
    protected function parseMultipartBody(string $rawBody, string $boundary, string &$bodyHtml, string &$bodyText, array &$attachments): void
    {
        $delimiter = "--" . $boundary;
        $sections = explode($delimiter, $rawBody);

        foreach ($sections as $section) {
            $section = trim($section);
            if ($section === '' || $section === '--') continue;

            $partSplit = explode("\r\n\r\n", $section, 2);
            if (count($partSplit) < 2) {
                $partSplit = explode("\n\n", $section, 2);
            }

            $partHeadersRaw = $partSplit[0] ?? '';
            $partBody = $partSplit[1] ?? '';

            $partHeaders = $this->parseRawHeaders($partHeadersRaw);
            $partContentType = $partHeaders['content-type'] ?? 'text/plain';
            $partTransferEncoding = strtolower($partHeaders['content-transfer-encoding'] ?? '');
            $partDisposition = $partHeaders['content-disposition'] ?? '';

            // Check nested multipart
            if (preg_match('/multipart\/[a-z]+;\s*boundary=(?:"([^"]+)"|([^\s;]+))/i', $partContentType, $nestedMatch)) {
                $nestedBoundary = $nestedMatch[1] ?: $nestedMatch[2];
                $this->parseMultipartBody($partBody, $nestedBoundary, $bodyHtml, $bodyText, $attachments);
                continue;
            }

            // Check if attachment
            $isAttachment = str_contains(strtolower($partDisposition), 'attachment') ||
                            preg_match('/filename=/i', $partDisposition) ||
                            preg_match('/name=/i', $partContentType);

            if ($isAttachment) {
                $filename = $this->extractFilename($partDisposition, $partContentType);
                $decodedData = $this->decodeContentTransfer($partBody, $partTransferEncoding);
                $mime = explode(';', $partContentType)[0] ?? 'application/octet-stream';

                $attachments[] = [
                    'index' => count($attachments),
                    'filename' => $this->decodeMimeHeader($filename),
                    'size' => strlen($decodedData),
                    'sizeFormatted' => $this->formatBytes(strlen($decodedData)),
                    'mime' => trim($mime),
                    'content' => base64_encode($decodedData)
                ];
                continue;
            }

            // Regular content body part
            $decodedBody = $this->decodeContentTransfer($partBody, $partTransferEncoding);
            $charset = $this->extractCharset($partContentType);
            if ($charset && strtoupper($charset) !== 'UTF-8') {
                $decodedBody = @mb_convert_encoding($decodedBody, 'UTF-8', $charset);
            }

            if (str_contains(strtolower($partContentType), 'text/html')) {
                if (empty($bodyHtml)) {
                    $bodyHtml = $decodedBody;
                }
            } elseif (str_contains(strtolower($partContentType), 'text/plain')) {
                if (empty($bodyText)) {
                    $bodyText = $decodedBody;
                }
            }
        }
    }

    /**
     * Decode transfer encoding (base64, quoted-printable, etc.)
     */
    protected function decodeContentTransfer(string $content, string $encoding): string
    {
        return match (strtolower(trim($encoding))) {
            'base64' => base64_decode($content),
            'quoted-printable' => quoted_printable_decode($content),
            default => $content
        };
    }

    /**
     * Extract charset from Content-Type
     */
    protected function extractCharset(string $contentType): ?string
    {
        if (preg_match('/charset=(?:"([^"]+)"|([^\s;]+))/i', $contentType, $m)) {
            return $m[1] ?: $m[2];
        }
        return null;
    }

    /**
     * Extract filename from headers
     */
    protected function extractFilename(string $disposition, string $contentType): string
    {
        if (preg_match('/filename\*=UTF-8\'\'([^;\s]+)/i', $disposition, $m)) {
            return urldecode($m[1]);
        }
        if (preg_match('/filename=(?:"([^"]+)"|([^\s;]+))/i', $disposition, $m)) {
            return $m[1] ?: $m[2];
        }
        if (preg_match('/name=(?:"([^"]+)"|([^\s;]+))/i', $contentType, $m)) {
            return $m[1] ?: $m[2];
        }
        return 'attachment_' . uniqid();
    }

    /**
     * Sanitize HTML content to prevent XSS while preserving styles
     */
    protected function sanitizeHtml(string $html): string
    {
        if (empty($html)) return '';

        // Remove dangerous script, iframe, object, embed tags
        $html = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);
        $html = preg_replace('#<iframe(.*?)>(.*?)</iframe>#is', '', $html);
        $html = preg_replace('#<object(.*?)>(.*?)</object>#is', '', $html);
        $html = preg_replace('#<embed(.*?)>(.*?)</embed>#is', '', $html);

        // Remove inline on* javascript event handlers (onclick, onload, etc.)
        $html = preg_replace('/(\son[a-zA-Z]+)=(["\'])(.*?)\2/i', '', $html);
        $html = preg_replace('/javascript:/i', 'blocked-js:', $html);

        return $html;
    }

    /**
     * Format bytes to readable string (KB, MB, GB)
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 1) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
