<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\MimeType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;

class MimeTypeController extends Controller
{
    /**
     * List MIME types for the authenticated user's account
     */
    public function index(Request $request)
    {
        $account = $request->user()->account;

        $mimeTypes = MimeType::where('account_id', $account->id)
            ->orderBy('extension')
            ->get();

        return $this->success([
            'mime_types' => $mimeTypes,
            'common_types' => MimeType::commonTypes(),
        ]);
    }

    /**
     * Add a custom MIME type
     */
    public function store(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'extension' => 'required|string|max:20|regex:/^[a-zA-Z0-9]+$/',
            'mime_type' => 'required|string|max:100',
            'handler' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $extension = strtolower($request->extension);

        // Check if already exists
        if (MimeType::where('account_id', $account->id)->where('extension', $extension)->exists()) {
            return $this->error('MIME type for this extension already exists', 422);
        }

        $mimeType = MimeType::create([
            'account_id' => $account->id,
            'extension' => $extension,
            'mime_type' => $request->mime_type,
            'handler' => $request->handler,
        ]);

        $this->syncMimeTypes($account);

        return $this->success($mimeType, 'MIME type added');
    }

    /**
     * Update a MIME type
     */
    public function update(Request $request, MimeType $mimeType)
    {
        $account = $request->user()->account;

        if ($mimeType->account_id !== $account->id) {
            return $this->error('MIME type not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'mime_type' => 'string|max:100',
            'handler' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $mimeType->update($request->only(['mime_type', 'handler']));
        $this->syncMimeTypes($account);

        return $this->success($mimeType, 'MIME type updated');
    }

    /**
     * Delete a MIME type
     */
    public function destroy(Request $request, MimeType $mimeType)
    {
        $account = $request->user()->account;

        if ($mimeType->account_id !== $account->id) {
            return $this->error('MIME type not found', 404);
        }

        $mimeType->delete();
        $this->syncMimeTypes($account);

        return $this->success(null, 'MIME type deleted');
    }

    /**
     * Get Apache handlers
     */
    public function handlers(Request $request)
    {
        $account = $request->user()->account;

        // Read current handlers from .htaccess
        $htaccessPath = "/home/{$account->username}/public_html/.htaccess";
        $handlers = [];

        if (file_exists($htaccessPath)) {
            $content = file_get_contents($htaccessPath);
            preg_match_all('/AddHandler\s+(\S+)\s+(.+)$/m', $content, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $extensions = preg_split('/\s+/', trim($match[2]));
                foreach ($extensions as $ext) {
                    $handlers[] = [
                        'handler' => $match[1],
                        'extension' => ltrim($ext, '.'),
                    ];
                }
            }
        }

        return $this->success([
            'handlers' => $handlers,
            'available_handlers' => $this->availableHandlers(),
        ]);
    }

    /**
     * Add Apache handler
     */
    public function addHandler(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'handler' => 'required|string|max:50',
            'extensions' => 'required|array|min:1',
            'extensions.*' => 'string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $htaccessPath = "/home/{$account->username}/public_html/.htaccess";
        $extensions = array_map(function ($ext) {
            return '.' . ltrim($ext, '.');
        }, $request->extensions);

        $handlerLine = "AddHandler {$request->handler} " . implode(' ', $extensions);

        $this->appendToHtaccess($htaccessPath, $handlerLine, $account);

        return $this->success(null, 'Handler added');
    }

    /**
     * Remove Apache handler
     */
    public function removeHandler(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'handler' => 'required|string',
            'extension' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $htaccessPath = "/home/{$account->username}/public_html/.htaccess";
        $extension = '.' . ltrim($request->extension, '.');

        if (file_exists($htaccessPath)) {
            $content = file_get_contents($htaccessPath);
            // Remove the specific handler line or just the extension from it
            $pattern = '/AddHandler\s+' . preg_quote($request->handler, '/') . '\s+[^\n]*' . preg_quote($extension, '/') . '[^\n]*/';
            $content = preg_replace($pattern, '', $content);
            $content = preg_replace("/\n{3,}/", "\n\n", $content); // Clean up extra newlines

            file_put_contents("/tmp/htaccess_tmp", $content);
            Process::run("sudo mv /tmp/htaccess_tmp {$htaccessPath}");
            Process::run("sudo chown {$account->username}:{$account->username} {$htaccessPath}");
        }

        return $this->success(null, 'Handler removed');
    }

    /**
     * Get directory index settings
     */
    public function indexes(Request $request)
    {
        $account = $request->user()->account;
        $path = $request->input('path', 'public_html');

        $fullPath = "/home/{$account->username}/{$path}";
        $htaccessPath = "{$fullPath}/.htaccess";

        $indexing = 'default';
        $indexFiles = ['index.html', 'index.php'];

        if (file_exists($htaccessPath)) {
            $content = file_get_contents($htaccessPath);

            if (preg_match('/Options\s+.*-Indexes/', $content)) {
                $indexing = 'disabled';
            } elseif (preg_match('/Options\s+.*\+?Indexes/', $content)) {
                $indexing = 'enabled';
            }

            if (preg_match('/DirectoryIndex\s+(.+)$/m', $content, $matches)) {
                $indexFiles = preg_split('/\s+/', trim($matches[1]));
            }
        }

        return $this->success([
            'path' => $path,
            'indexing' => $indexing,
            'index_files' => $indexFiles,
        ]);
    }

    /**
     * Update directory index settings
     */
    public function updateIndexes(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
            'indexing' => 'required|in:default,enabled,disabled',
            'index_files' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $fullPath = "/home/{$account->username}/{$request->path}";
        $htaccessPath = "{$fullPath}/.htaccess";

        // Security check - ensure path is within user's home
        $realPath = realpath($fullPath);
        $homeDir = "/home/{$account->username}";
        if (!$realPath || !str_starts_with($realPath, $homeDir)) {
            return $this->error('Invalid path', 400);
        }

        $directives = [];

        if ($request->indexing === 'disabled') {
            $directives[] = 'Options -Indexes';
        } elseif ($request->indexing === 'enabled') {
            $directives[] = 'Options +Indexes';
        }

        if ($request->index_files && count($request->index_files) > 0) {
            $directives[] = 'DirectoryIndex ' . implode(' ', $request->index_files);
        }

        // Update .htaccess
        $this->updateHtaccessSection($htaccessPath, 'DIRECTORY_INDEXES', $directives, $account);

        return $this->success(null, 'Index settings updated');
    }

    /**
     * Sync MIME types to .htaccess
     */
    protected function syncMimeTypes($account): void
    {
        $mimeTypes = MimeType::where('account_id', $account->id)->get();
        $htaccessPath = "/home/{$account->username}/public_html/.htaccess";

        $directives = [];
        foreach ($mimeTypes as $type) {
            $directives[] = "AddType {$type->mime_type} .{$type->extension}";
        }

        $this->updateHtaccessSection($htaccessPath, 'MIME_TYPES', $directives, $account);
    }

    /**
     * Update a section in .htaccess
     */
    protected function updateHtaccessSection(string $path, string $section, array $directives, $account): void
    {
        $content = '';
        if (file_exists($path)) {
            $content = file_get_contents($path);
        }

        $startMarker = "# BEGIN {$section}";
        $endMarker = "# END {$section}";

        // Remove existing section
        $pattern = "/{$startMarker}.*?{$endMarker}\n?/s";
        $content = preg_replace($pattern, '', $content);

        // Add new section if there are directives
        if (!empty($directives)) {
            $newSection = "{$startMarker}\n" . implode("\n", $directives) . "\n{$endMarker}\n";
            $content = trim($content) . "\n\n" . $newSection;
        }

        file_put_contents("/tmp/htaccess_tmp", $content);
        Process::run("sudo mv /tmp/htaccess_tmp {$path}");
        Process::run("sudo chown {$account->username}:{$account->username} {$path}");
        Process::run("sudo chmod 644 {$path}");
    }

    /**
     * Append to .htaccess
     */
    protected function appendToHtaccess(string $path, string $line, $account): void
    {
        $content = '';
        if (file_exists($path)) {
            $content = file_get_contents($path);
        }

        $content = trim($content) . "\n" . $line . "\n";

        file_put_contents("/tmp/htaccess_tmp", $content);
        Process::run("sudo mv /tmp/htaccess_tmp {$path}");
        Process::run("sudo chown {$account->username}:{$account->username} {$path}");
    }

    /**
     * Available Apache handlers
     */
    protected function availableHandlers(): array
    {
        return [
            'cgi-script' => 'CGI Script',
            'server-parsed' => 'Server Side Includes',
            'application/x-httpd-php' => 'PHP',
            'application/x-httpd-php-source' => 'PHP Source',
            'text/html' => 'HTML',
            'text/plain' => 'Plain Text',
            'application/x-tar' => 'Tarball',
            'application/x-gzip' => 'Gzip',
        ];
    }
}
