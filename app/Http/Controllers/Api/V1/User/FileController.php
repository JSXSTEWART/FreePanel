<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Services\FileManager\FileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    protected FileService $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    public function list(Request $request)
    {
        $account = $request->user()->account;
        $path = $request->get('path', '/');

        // Ensure path is within user's home directory
        $basePath = "/home/{$account->username}";
        $fullPath = $this->resolvePath($basePath, $path);

        if (!str_starts_with($fullPath, $basePath)) {
            return $this->error('Access denied', 403);
        }

        try {
            $items = $this->fileService->listDirectory($fullPath);
            return $this->success([
                'path' => str_replace($basePath, '', $fullPath) ?: '/',
                'items' => $items,
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to list directory: ' . $e->getMessage(), 500);
        }
    }

    public function read(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $basePath = "/home/{$account->username}";
        $fullPath = $this->resolvePath($basePath, $request->path);

        if (!str_starts_with($fullPath, $basePath)) {
            return $this->error('Access denied', 403);
        }

        try {
            $content = $this->fileService->readFile($fullPath);
            $info = $this->fileService->getFileInfo($fullPath);

            return $this->success([
                'path' => str_replace($basePath, '', $fullPath),
                'content' => $content,
                'info' => $info,
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to read file: ' . $e->getMessage(), 500);
        }
    }

    public function write(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $basePath = "/home/{$account->username}";
        $fullPath = $this->resolvePath($basePath, $request->path);

        if (!str_starts_with($fullPath, $basePath)) {
            return $this->error('Access denied', 403);
        }

        // Check disk quota
        $contentSize = strlen($request->content);
        $currentUsage = $this->fileService->getDirectorySize($basePath);
        if ($account->package->disk_quota != -1 && ($currentUsage + $contentSize) > ($account->package->disk_quota * 1024 * 1024)) {
            return $this->error('Disk quota exceeded', 403);
        }

        try {
            $this->fileService->writeFile($fullPath, $request->content, $account->uid, $account->gid);
            return $this->success(null, 'File saved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to write file: ' . $e->getMessage(), 500);
        }
    }

    public function upload(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
            'file' => 'required|file|max:102400', // 100MB max
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $basePath = "/home/{$account->username}";
        $destPath = $this->resolvePath($basePath, $request->path);

        if (!str_starts_with($destPath, $basePath)) {
            return $this->error('Access denied', 403);
        }

        $file = $request->file('file');

        // Check disk quota
        $currentUsage = $this->fileService->getDirectorySize($basePath);
        if ($account->package->disk_quota != -1 && ($currentUsage + $file->getSize()) > ($account->package->disk_quota * 1024 * 1024)) {
            return $this->error('Disk quota exceeded', 403);
        }

        try {
            $fullPath = rtrim($destPath, '/') . '/' . $file->getClientOriginalName();
            $this->fileService->uploadFile($file, $fullPath, $account->uid, $account->gid);
            return $this->success(null, 'File uploaded successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to upload file: ' . $e->getMessage(), 500);
        }
    }

    public function download(Request $request): StreamedResponse
    {
        $account = $request->user()->account;

        $path = $request->get('path');
        if (!$path) {
            abort(400, 'Path is required');
        }

        $basePath = "/home/{$account->username}";
        $fullPath = $this->resolvePath($basePath, $path);

        if (!str_starts_with($fullPath, $basePath)) {
            abort(403, 'Access denied');
        }

        if (!file_exists($fullPath) || is_dir($fullPath)) {
            abort(404, 'File not found');
        }

        return response()->streamDownload(function () use ($fullPath) {
            $stream = fopen($fullPath, 'rb');
            while (!feof($stream)) {
                echo fread($stream, 8192);
            }
            fclose($stream);
        }, basename($fullPath));
    }

    public function mkdir(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
            'name' => 'required|string|max:255|regex:/^[^\/\0]+$/',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $basePath = "/home/{$account->username}";
        $parentPath = $this->resolvePath($basePath, $request->path);
        $fullPath = rtrim($parentPath, '/') . '/' . $request->name;

        if (!str_starts_with($fullPath, $basePath)) {
            return $this->error('Access denied', 403);
        }

        try {
            $this->fileService->createDirectory($fullPath, $account->uid, $account->gid);
            return $this->success(null, 'Directory created successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to create directory: ' . $e->getMessage(), 500);
        }
    }

    public function delete(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $basePath = "/home/{$account->username}";
        $fullPath = $this->resolvePath($basePath, $request->path);

        if (!str_starts_with($fullPath, $basePath)) {
            return $this->error('Access denied', 403);
        }

        // Prevent deleting critical directories
        $protectedPaths = [
            $basePath,
            "$basePath/public_html",
            "$basePath/mail",
            "$basePath/logs",
        ];

        if (in_array($fullPath, $protectedPaths)) {
            return $this->error('Cannot delete protected directory', 403);
        }

        try {
            $this->fileService->delete($fullPath);
            return $this->success(null, 'Item deleted successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to delete: ' . $e->getMessage(), 500);
        }
    }

    public function copy(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'source' => 'required|string',
            'destination' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $basePath = "/home/{$account->username}";
        $sourcePath = $this->resolvePath($basePath, $request->source);
        $destPath = $this->resolvePath($basePath, $request->destination);

        if (!str_starts_with($sourcePath, $basePath) || !str_starts_with($destPath, $basePath)) {
            return $this->error('Access denied', 403);
        }

        // Check disk quota
        $sourceSize = is_dir($sourcePath)
            ? $this->fileService->getDirectorySize($sourcePath)
            : filesize($sourcePath);
        $currentUsage = $this->fileService->getDirectorySize($basePath);

        if ($account->package->disk_quota != -1 && ($currentUsage + $sourceSize) > ($account->package->disk_quota * 1024 * 1024)) {
            return $this->error('Disk quota exceeded', 403);
        }

        try {
            $this->fileService->copy($sourcePath, $destPath, $account->uid, $account->gid);
            return $this->success(null, 'Item copied successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to copy: ' . $e->getMessage(), 500);
        }
    }

    public function move(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'source' => 'required|string',
            'destination' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $basePath = "/home/{$account->username}";
        $sourcePath = $this->resolvePath($basePath, $request->source);
        $destPath = $this->resolvePath($basePath, $request->destination);

        if (!str_starts_with($sourcePath, $basePath) || !str_starts_with($destPath, $basePath)) {
            return $this->error('Access denied', 403);
        }

        try {
            $this->fileService->move($sourcePath, $destPath);
            return $this->success(null, 'Item moved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to move: ' . $e->getMessage(), 500);
        }
    }

    public function rename(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
            'name' => 'required|string|max:255|regex:/^[^\/\0]+$/',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $basePath = "/home/{$account->username}";
        $fullPath = $this->resolvePath($basePath, $request->path);
        $newPath = dirname($fullPath) . '/' . $request->name;

        if (!str_starts_with($fullPath, $basePath) || !str_starts_with($newPath, $basePath)) {
            return $this->error('Access denied', 403);
        }

        try {
            $this->fileService->rename($fullPath, $newPath);
            return $this->success(null, 'Item renamed successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to rename: ' . $e->getMessage(), 500);
        }
    }

    public function chmod(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
            'permissions' => 'required|string|regex:/^[0-7]{3,4}$/',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $basePath = "/home/{$account->username}";
        $fullPath = $this->resolvePath($basePath, $request->path);

        if (!str_starts_with($fullPath, $basePath)) {
            return $this->error('Access denied', 403);
        }

        try {
            $this->fileService->chmod($fullPath, octdec($request->permissions));
            return $this->success(null, 'Permissions updated successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to change permissions: ' . $e->getMessage(), 500);
        }
    }

    public function compress(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'paths' => 'required|array',
            'paths.*' => 'string',
            'destination' => 'required|string',
            'type' => 'nullable|string|in:zip,tar.gz',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $basePath = "/home/{$account->username}";
        $destPath = $this->resolvePath($basePath, $request->destination);

        if (!str_starts_with($destPath, $basePath)) {
            return $this->error('Access denied', 403);
        }

        $sourcePaths = [];
        foreach ($request->paths as $path) {
            $fullPath = $this->resolvePath($basePath, $path);
            if (!str_starts_with($fullPath, $basePath)) {
                return $this->error('Access denied', 403);
            }
            $sourcePaths[] = $fullPath;
        }

        try {
            $this->fileService->compress($sourcePaths, $destPath, $request->type ?? 'zip');
            return $this->success(null, 'Files compressed successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to compress: ' . $e->getMessage(), 500);
        }
    }

    public function extract(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
            'destination' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $basePath = "/home/{$account->username}";
        $archivePath = $this->resolvePath($basePath, $request->path);
        $destPath = $this->resolvePath($basePath, $request->destination);

        if (!str_starts_with($archivePath, $basePath) || !str_starts_with($destPath, $basePath)) {
            return $this->error('Access denied', 403);
        }

        try {
            $this->fileService->extract($archivePath, $destPath, $account->uid, $account->gid);
            return $this->success(null, 'Archive extracted successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to extract: ' . $e->getMessage(), 500);
        }
    }

    protected function resolvePath(string $basePath, string $path): string
    {
        // Handle absolute vs relative paths
        if (str_starts_with($path, '/')) {
            $fullPath = $basePath . $path;
        } else {
            $fullPath = $basePath . '/' . $path;
        }

        // Resolve . and ..
        $parts = [];
        foreach (explode('/', $fullPath) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
            } else {
                $parts[] = $part;
            }
        }

        return '/' . implode('/', $parts);
    }
}
