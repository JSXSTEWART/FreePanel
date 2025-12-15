<?php

namespace App\Services\FileManager;

use Illuminate\Support\Facades\File;
use Illuminate\Http\UploadedFile;

class FileService
{
    /**
     * List contents of a directory
     */
    public function listDirectory(string $path): array
    {
        if (!File::isDirectory($path)) {
            throw new \InvalidArgumentException("Directory does not exist: {$path}");
        }

        $items = [];
        $entries = scandir($path);

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = "{$path}/{$entry}";
            $items[] = $this->getFileInfo($fullPath);
        }

        // Sort: directories first, then by name
        usort($items, function ($a, $b) {
            if ($a['is_dir'] !== $b['is_dir']) {
                return $b['is_dir'] - $a['is_dir'];
            }
            return strcasecmp($a['name'], $b['name']);
        });

        return $items;
    }

    /**
     * Get file/directory info
     */
    public function getFileInfo(string $path): array
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("Path does not exist: {$path}");
        }

        $stat = stat($path);
        $isDir = is_dir($path);

        return [
            'name' => basename($path),
            'path' => $path,
            'is_dir' => $isDir,
            'is_file' => !$isDir,
            'size' => $isDir ? 0 : $stat['size'],
            'permissions' => $this->formatPermissions($stat['mode']),
            'permissions_octal' => substr(sprintf('%o', $stat['mode']), -4),
            'owner' => posix_getpwuid($stat['uid'])['name'] ?? $stat['uid'],
            'group' => posix_getgrgid($stat['gid'])['name'] ?? $stat['gid'],
            'modified' => date('Y-m-d H:i:s', $stat['mtime']),
            'accessed' => date('Y-m-d H:i:s', $stat['atime']),
            'created' => date('Y-m-d H:i:s', $stat['ctime']),
            'mime_type' => $isDir ? 'directory' : $this->getMimeType($path),
            'extension' => $isDir ? null : pathinfo($path, PATHINFO_EXTENSION),
        ];
    }

    /**
     * Read file contents
     */
    public function readFile(string $path): string
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("File does not exist: {$path}");
        }

        if (is_dir($path)) {
            throw new \InvalidArgumentException("Path is a directory: {$path}");
        }

        // Check file size (limit to 10MB for reading)
        if (filesize($path) > 10 * 1024 * 1024) {
            throw new \InvalidArgumentException("File too large to read");
        }

        return file_get_contents($path);
    }

    /**
     * Write file contents
     */
    public function writeFile(string $path, string $content, int $uid, int $gid): void
    {
        $dir = dirname($path);

        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
            chown($dir, $uid);
            chgrp($dir, $gid);
        }

        file_put_contents($path, $content);
        chown($path, $uid);
        chgrp($path, $gid);
    }

    /**
     * Upload a file
     */
    public function uploadFile(UploadedFile $file, string $destination, int $uid, int $gid): void
    {
        $dir = dirname($destination);

        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
            chown($dir, $uid);
            chgrp($dir, $gid);
        }

        $file->move($dir, basename($destination));

        chown($destination, $uid);
        chgrp($destination, $gid);
    }

    /**
     * Create a directory
     */
    public function createDirectory(string $path, int $uid, int $gid): void
    {
        if (file_exists($path)) {
            throw new \InvalidArgumentException("Path already exists: {$path}");
        }

        File::makeDirectory($path, 0755, true);
        chown($path, $uid);
        chgrp($path, $gid);
    }

    /**
     * Delete a file or directory
     */
    public function delete(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_dir($path)) {
            File::deleteDirectory($path);
        } else {
            File::delete($path);
        }
    }

    /**
     * Copy a file or directory
     */
    public function copy(string $source, string $destination, int $uid, int $gid): void
    {
        if (!file_exists($source)) {
            throw new \InvalidArgumentException("Source does not exist: {$source}");
        }

        if (is_dir($source)) {
            File::copyDirectory($source, $destination);
        } else {
            File::copy($source, $destination);
        }

        $this->chownRecursive($destination, $uid, $gid);
    }

    /**
     * Move/rename a file or directory
     */
    public function move(string $source, string $destination): void
    {
        if (!file_exists($source)) {
            throw new \InvalidArgumentException("Source does not exist: {$source}");
        }

        File::move($source, $destination);
    }

    /**
     * Rename a file or directory
     */
    public function rename(string $path, string $newPath): void
    {
        $this->move($path, $newPath);
    }

    /**
     * Change permissions
     */
    public function chmod(string $path, int $mode): void
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("Path does not exist: {$path}");
        }

        chmod($path, $mode);
    }

    /**
     * Compress files/directories
     */
    public function compress(array $paths, string $destination, string $type = 'zip'): void
    {
        $dir = dirname($destination);

        switch ($type) {
            case 'zip':
                $zip = new \ZipArchive();
                if ($zip->open($destination, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                    throw new \RuntimeException("Cannot create zip file");
                }

                foreach ($paths as $path) {
                    if (is_dir($path)) {
                        $this->addDirectoryToZip($zip, $path, basename($path));
                    } else {
                        $zip->addFile($path, basename($path));
                    }
                }

                $zip->close();
                break;

            case 'tar.gz':
                $fileList = implode(' ', array_map('escapeshellarg', $paths));
                exec("tar -czf " . escapeshellarg($destination) . " -C " . escapeshellarg($dir) . " {$fileList}", $output, $returnCode);

                if ($returnCode !== 0) {
                    throw new \RuntimeException("Failed to create tar.gz archive");
                }
                break;

            default:
                throw new \InvalidArgumentException("Unsupported compression type: {$type}");
        }
    }

    /**
     * Extract an archive
     */
    public function extract(string $archivePath, string $destination, int $uid, int $gid): void
    {
        if (!file_exists($archivePath)) {
            throw new \InvalidArgumentException("Archive does not exist: {$archivePath}");
        }

        if (!File::isDirectory($destination)) {
            File::makeDirectory($destination, 0755, true);
        }

        $extension = strtolower(pathinfo($archivePath, PATHINFO_EXTENSION));

        switch ($extension) {
            case 'zip':
                $zip = new \ZipArchive();
                if ($zip->open($archivePath) !== true) {
                    throw new \RuntimeException("Cannot open zip file");
                }
                $zip->extractTo($destination);
                $zip->close();
                break;

            case 'gz':
            case 'tgz':
                exec("tar -xzf " . escapeshellarg($archivePath) . " -C " . escapeshellarg($destination), $output, $returnCode);
                if ($returnCode !== 0) {
                    throw new \RuntimeException("Failed to extract tar.gz archive");
                }
                break;

            case 'bz2':
                exec("tar -xjf " . escapeshellarg($archivePath) . " -C " . escapeshellarg($destination), $output, $returnCode);
                if ($returnCode !== 0) {
                    throw new \RuntimeException("Failed to extract tar.bz2 archive");
                }
                break;

            default:
                throw new \InvalidArgumentException("Unsupported archive type: {$extension}");
        }

        $this->chownRecursive($destination, $uid, $gid);
    }

    /**
     * Get directory size
     */
    public function getDirectorySize(string $path): int
    {
        if (!File::isDirectory($path)) {
            return 0;
        }

        $result = exec("du -sb " . escapeshellarg($path) . " 2>/dev/null | cut -f1");
        return (int) $result;
    }

    protected function formatPermissions(int $mode): string
    {
        $perms = '';

        // File type
        if (($mode & 0xC000) === 0xC000) {
            $perms = 's'; // Socket
        } elseif (($mode & 0xA000) === 0xA000) {
            $perms = 'l'; // Symbolic Link
        } elseif (($mode & 0x8000) === 0x8000) {
            $perms = '-'; // Regular
        } elseif (($mode & 0x6000) === 0x6000) {
            $perms = 'b'; // Block special
        } elseif (($mode & 0x4000) === 0x4000) {
            $perms = 'd'; // Directory
        } elseif (($mode & 0x2000) === 0x2000) {
            $perms = 'c'; // Character special
        } elseif (($mode & 0x1000) === 0x1000) {
            $perms = 'p'; // FIFO pipe
        } else {
            $perms = 'u'; // Unknown
        }

        // Owner
        $perms .= (($mode & 0x0100) ? 'r' : '-');
        $perms .= (($mode & 0x0080) ? 'w' : '-');
        $perms .= (($mode & 0x0040) ? (($mode & 0x0800) ? 's' : 'x') : (($mode & 0x0800) ? 'S' : '-'));

        // Group
        $perms .= (($mode & 0x0020) ? 'r' : '-');
        $perms .= (($mode & 0x0010) ? 'w' : '-');
        $perms .= (($mode & 0x0008) ? (($mode & 0x0400) ? 's' : 'x') : (($mode & 0x0400) ? 'S' : '-'));

        // Other
        $perms .= (($mode & 0x0004) ? 'r' : '-');
        $perms .= (($mode & 0x0002) ? 'w' : '-');
        $perms .= (($mode & 0x0001) ? (($mode & 0x0200) ? 't' : 'x') : (($mode & 0x0200) ? 'T' : '-'));

        return $perms;
    }

    protected function getMimeType(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $path);
        finfo_close($finfo);

        return $mimeType ?: 'application/octet-stream';
    }

    protected function addDirectoryToZip(\ZipArchive $zip, string $dir, string $base): void
    {
        $files = scandir($dir);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $fullPath = "{$dir}/{$file}";
            $zipPath = "{$base}/{$file}";

            if (is_dir($fullPath)) {
                $zip->addEmptyDir($zipPath);
                $this->addDirectoryToZip($zip, $fullPath, $zipPath);
            } else {
                $zip->addFile($fullPath, $zipPath);
            }
        }
    }

    protected function chownRecursive(string $path, int $uid, int $gid): void
    {
        chown($path, $uid);
        chgrp($path, $gid);

        if (is_dir($path)) {
            $entries = scandir($path);
            foreach ($entries as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $this->chownRecursive("{$path}/{$entry}", $uid, $gid);
                }
            }
        }
    }
}
