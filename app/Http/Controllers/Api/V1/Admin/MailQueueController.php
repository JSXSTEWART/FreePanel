<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;

class MailQueueController extends Controller
{
    /**
     * Get mail queue summary
     */
    public function index(Request $request)
    {
        // Get queue statistics
        $queueStats = $this->getQueueStats();

        // Get queue entries with pagination
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 50);

        $queue = $this->getQueueEntries($page, $perPage);

        return $this->success([
            'stats' => $queueStats,
            'queue' => $queue['entries'],
            'total' => $queue['total'],
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * Get detailed view of a queued message
     */
    public function show(string $queueId)
    {
        $result = Process::run("sudo /usr/sbin/postcat -q {$queueId} 2>&1");

        if (!$result->successful()) {
            return $this->error('Message not found in queue', 404);
        }

        $message = $this->parseQueuedMessage($result->output());

        return $this->success($message);
    }

    /**
     * Delete a queued message
     */
    public function destroy(string $queueId)
    {
        $result = Process::run("sudo /usr/sbin/postsuper -d {$queueId} 2>&1");

        if (!$result->successful()) {
            return $this->error('Failed to delete message: ' . $result->errorOutput(), 500);
        }

        return $this->success(null, 'Message deleted from queue');
    }

    /**
     * Delete multiple queued messages
     */
    public function bulkDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'queue_ids' => 'required|array',
            'queue_ids.*' => 'string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $deleted = 0;
        $failed = 0;

        foreach ($request->queue_ids as $queueId) {
            $result = Process::run("sudo /usr/sbin/postsuper -d {$queueId} 2>&1");
            if ($result->successful()) {
                $deleted++;
            } else {
                $failed++;
            }
        }

        return $this->success([
            'deleted' => $deleted,
            'failed' => $failed,
        ], "{$deleted} messages deleted");
    }

    /**
     * Flush the entire queue (attempt redelivery)
     */
    public function flush()
    {
        $result = Process::run("sudo /usr/sbin/postqueue -f 2>&1");

        return $this->success(null, 'Queue flush initiated');
    }

    /**
     * Delete all messages in queue
     */
    public function purge(Request $request)
    {
        $type = $request->input('type', 'all'); // all, deferred, hold

        switch ($type) {
            case 'deferred':
                $result = Process::run("sudo /usr/sbin/postsuper -d ALL deferred 2>&1");
                break;
            case 'hold':
                $result = Process::run("sudo /usr/sbin/postsuper -d ALL hold 2>&1");
                break;
            default:
                $result = Process::run("sudo /usr/sbin/postsuper -d ALL 2>&1");
        }

        return $this->success(null, 'Queue purged');
    }

    /**
     * Hold a message
     */
    public function hold(string $queueId)
    {
        $result = Process::run("sudo /usr/sbin/postsuper -h {$queueId} 2>&1");

        if (!$result->successful()) {
            return $this->error('Failed to hold message', 500);
        }

        return $this->success(null, 'Message put on hold');
    }

    /**
     * Release a held message
     */
    public function release(string $queueId)
    {
        $result = Process::run("sudo /usr/sbin/postsuper -H {$queueId} 2>&1");

        if (!$result->successful()) {
            return $this->error('Failed to release message', 500);
        }

        return $this->success(null, 'Message released from hold');
    }

    /**
     * Requeue a message (move to active queue)
     */
    public function requeue(string $queueId)
    {
        $result = Process::run("sudo /usr/sbin/postsuper -r {$queueId} 2>&1");

        if (!$result->successful()) {
            return $this->error('Failed to requeue message', 500);
        }

        return $this->success(null, 'Message requeued');
    }

    /**
     * Search queue by sender or recipient
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:3',
            'type' => 'in:sender,recipient,all',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $query = $request->query;
        $type = $request->input('type', 'all');

        $result = Process::run("sudo /usr/sbin/postqueue -j 2>/dev/null");
        $entries = [];

        foreach (explode("\n", $result->output()) as $line) {
            if (empty(trim($line))) continue;

            $entry = json_decode($line, true);
            if (!$entry) continue;

            $match = false;

            if ($type === 'all' || $type === 'sender') {
                if (stripos($entry['sender'] ?? '', $query) !== false) {
                    $match = true;
                }
            }

            if ($type === 'all' || $type === 'recipient') {
                foreach ($entry['recipients'] ?? [] as $recipient) {
                    if (stripos($recipient['address'] ?? '', $query) !== false) {
                        $match = true;
                        break;
                    }
                }
            }

            if ($match) {
                $entries[] = $this->formatQueueEntry($entry);
            }
        }

        return $this->success([
            'query' => $query,
            'results' => $entries,
            'count' => count($entries),
        ]);
    }

    /**
     * Get queue by sender domain
     */
    public function bySender(Request $request)
    {
        $domain = $request->input('domain');

        $result = Process::run("sudo /usr/sbin/postqueue -j 2>/dev/null");
        $bySender = [];

        foreach (explode("\n", $result->output()) as $line) {
            if (empty(trim($line))) continue;

            $entry = json_decode($line, true);
            if (!$entry) continue;

            $sender = $entry['sender'] ?? 'MAILER-DAEMON';
            $senderDomain = substr(strrchr($sender, '@'), 1) ?: 'local';

            if ($domain && $senderDomain !== $domain) continue;

            if (!isset($bySender[$senderDomain])) {
                $bySender[$senderDomain] = [
                    'domain' => $senderDomain,
                    'count' => 0,
                    'size' => 0,
                ];
            }

            $bySender[$senderDomain]['count']++;
            $bySender[$senderDomain]['size'] += $entry['message_size'] ?? 0;
        }

        // Sort by count descending
        usort($bySender, fn($a, $b) => $b['count'] - $a['count']);

        return $this->success([
            'by_sender' => array_values($bySender),
        ]);
    }

    /**
     * Get mail logs
     */
    public function logs(Request $request)
    {
        $lines = $request->input('lines', 100);
        $filter = $request->input('filter');

        $cmd = "sudo tail -{$lines} /var/log/mail.log";

        if ($filter) {
            $cmd .= " | grep -i " . escapeshellarg($filter);
        }

        $result = Process::run($cmd . " 2>/dev/null");

        $logs = [];
        foreach (explode("\n", $result->output()) as $line) {
            if (empty(trim($line))) continue;
            $logs[] = $this->parseMailLogLine($line);
        }

        return $this->success([
            'logs' => array_reverse($logs),
        ]);
    }

    /**
     * Get queue statistics
     */
    protected function getQueueStats(): array
    {
        // Get queue counts
        $result = Process::run("sudo /usr/sbin/postqueue -p 2>/dev/null | tail -1");
        $lastLine = trim($result->output());

        $active = 0;
        $deferred = 0;
        $hold = 0;
        $totalSize = 0;

        // Parse "-- 5 Kbytes in 2 Requests."
        if (preg_match('/(\d+)\s+\w+bytes?\s+in\s+(\d+)\s+Requests?/i', $lastLine, $matches)) {
            $totalSize = (int) $matches[1] * 1024;
        }

        // Count by queue type
        $result = Process::run("sudo find /var/spool/postfix/active -type f 2>/dev/null | wc -l");
        $active = (int) trim($result->output());

        $result = Process::run("sudo find /var/spool/postfix/deferred -type f 2>/dev/null | wc -l");
        $deferred = (int) trim($result->output());

        $result = Process::run("sudo find /var/spool/postfix/hold -type f 2>/dev/null | wc -l");
        $hold = (int) trim($result->output());

        return [
            'total' => $active + $deferred + $hold,
            'active' => $active,
            'deferred' => $deferred,
            'hold' => $hold,
            'total_size' => $totalSize,
            'total_size_human' => $this->formatBytes($totalSize),
        ];
    }

    /**
     * Get queue entries
     */
    protected function getQueueEntries(int $page, int $perPage): array
    {
        $result = Process::run("sudo /usr/sbin/postqueue -j 2>/dev/null");
        $entries = [];

        foreach (explode("\n", $result->output()) as $line) {
            if (empty(trim($line))) continue;

            $entry = json_decode($line, true);
            if ($entry) {
                $entries[] = $this->formatQueueEntry($entry);
            }
        }

        $total = count($entries);
        $offset = ($page - 1) * $perPage;
        $entries = array_slice($entries, $offset, $perPage);

        return [
            'entries' => $entries,
            'total' => $total,
        ];
    }

    /**
     * Format queue entry
     */
    protected function formatQueueEntry(array $entry): array
    {
        $recipients = [];
        foreach ($entry['recipients'] ?? [] as $recipient) {
            $recipients[] = [
                'address' => $recipient['address'] ?? '',
                'delay_reason' => $recipient['delay_reason'] ?? null,
            ];
        }

        return [
            'queue_id' => $entry['queue_id'] ?? '',
            'queue_name' => $entry['queue_name'] ?? '',
            'arrival_time' => isset($entry['arrival_time']) ? date('Y-m-d H:i:s', $entry['arrival_time']) : null,
            'message_size' => $entry['message_size'] ?? 0,
            'message_size_human' => $this->formatBytes($entry['message_size'] ?? 0),
            'sender' => $entry['sender'] ?? '',
            'recipients' => $recipients,
        ];
    }

    /**
     * Parse queued message details
     */
    protected function parseQueuedMessage(string $output): array
    {
        $message = [
            'headers' => [],
            'body_preview' => '',
        ];

        $lines = explode("\n", $output);
        $inHeaders = false;
        $inBody = false;
        $bodyLines = [];

        foreach ($lines as $line) {
            if (str_starts_with($line, '*** MESSAGE CONTENTS')) {
                $inHeaders = true;
                continue;
            }

            if ($inHeaders && empty(trim($line))) {
                $inHeaders = false;
                $inBody = true;
                continue;
            }

            if ($inHeaders) {
                if (preg_match('/^([^:]+):\s*(.*)$/', $line, $matches)) {
                    $message['headers'][$matches[1]] = $matches[2];
                }
            }

            if ($inBody && count($bodyLines) < 50) {
                $bodyLines[] = $line;
            }
        }

        $message['body_preview'] = implode("\n", $bodyLines);

        return $message;
    }

    /**
     * Parse mail log line
     */
    protected function parseMailLogLine(string $line): array
    {
        $parsed = [
            'raw' => $line,
            'timestamp' => null,
            'service' => null,
            'queue_id' => null,
            'message' => null,
        ];

        // Parse timestamp
        if (preg_match('/^(\w+\s+\d+\s+[\d:]+)/', $line, $matches)) {
            $parsed['timestamp'] = $matches[1];
        }

        // Parse service
        if (preg_match('/postfix\/(\w+)\[/', $line, $matches)) {
            $parsed['service'] = $matches[1];
        }

        // Parse queue ID
        if (preg_match('/:\s+([A-F0-9]+):/', $line, $matches)) {
            $parsed['queue_id'] = $matches[1];
        }

        // Get message portion
        if (preg_match('/\]:\s+(.+)$/', $line, $matches)) {
            $parsed['message'] = $matches[1];
        }

        return $parsed;
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
