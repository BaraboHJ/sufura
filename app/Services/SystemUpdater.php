<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use ZipArchive;

class SystemUpdater
{
    private string $projectRoot;
    private string $repoZipUrl;
    /** @var array<string, bool> */
    private array $excludeMap;

    /**
     * @param string[] $excludePaths
     */
    public function __construct(string $projectRoot, string $repoZipUrl, array $excludePaths = [])
    {
        $this->projectRoot = rtrim($projectRoot, '/');
        $this->repoZipUrl = $repoZipUrl;

        $defaultExcludes = [
            '.git',
            '.gitignore',
            '.env',
            'config/config.php',
            'storage',
            'uploads',
            'tmp',
            'sufura-main.zip',
        ];

        $this->excludeMap = [];
        foreach (array_merge($defaultExcludes, $excludePaths) as $path) {
            $normalized = trim(str_replace('\\', '/', (string) $path), '/');
            if ($normalized !== '') {
                $this->excludeMap[$normalized] = true;
            }
        }
    }

    /**
     * @return array{files_updated:int, source_root:string}
     */
    public function run(): array
    {
        if (!extension_loaded('curl')) {
            throw new RuntimeException('PHP cURL extension is required for updates.');
        }

        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ZipArchive extension is required for updates.');
        }

        $lockPath = $this->projectRoot . '/tmp/.update.lock';
        $this->ensureDirectory(dirname($lockPath));

        $lockHandle = fopen($lockPath, 'c');
        if (!$lockHandle) {
            throw new RuntimeException('Could not open updater lock file.');
        }

        if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
            fclose($lockHandle);
            throw new RuntimeException('An update is already in progress. Please try again shortly.');
        }

        $workDir = $this->projectRoot . '/tmp/updater-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $this->ensureDirectory($workDir);

        $zipFile = $workDir . '/release.zip';

        try {
            $this->downloadZip($zipFile);
            $sourceRoot = $this->extractZip($zipFile, $workDir . '/extract');
            $updatedCount = $this->syncDirectories($sourceRoot, $this->projectRoot);

            return [
                'files_updated' => $updatedCount,
                'source_root' => $sourceRoot,
            ];
        } finally {
            $this->deleteRecursively($workDir);
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    private function downloadZip(string $targetPath): void
    {
        $fp = fopen($targetPath, 'w');
        if (!$fp) {
            throw new RuntimeException('Could not create temporary ZIP file.');
        }

        $ch = curl_init($this->repoZipUrl);
        if ($ch === false) {
            fclose($fp);
            throw new RuntimeException('Could not initialize cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FAILONERROR => true,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_USERAGENT => 'SufuraUpdater/1.0',
        ]);

        $ok = curl_exec($ch);
        if ($ok !== true) {
            $err = curl_error($ch);
            curl_close($ch);
            fclose($fp);
            throw new RuntimeException('Download failed: ' . $err);
        }

        $statusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        fclose($fp);

        if ($statusCode !== 200) {
            throw new RuntimeException('Unexpected HTTP status downloading update: ' . $statusCode);
        }
    }

    private function extractZip(string $zipPath, string $extractPath): string
    {
        $this->ensureDirectory($extractPath);

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Could not open downloaded ZIP archive.');
        }

        if (!$zip->extractTo($extractPath)) {
            $zip->close();
            throw new RuntimeException('Could not extract update ZIP archive.');
        }
        $zip->close();

        $entries = scandir($extractPath);
        if ($entries === false) {
            throw new RuntimeException('Could not scan extracted update folder.');
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $candidate = $extractPath . '/' . $entry;
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Could not locate extracted source folder.');
    }

    private function syncDirectories(string $sourceDir, string $destDir): int
    {
        $entries = scandir($sourceDir);
        if ($entries === false) {
            throw new RuntimeException('Could not scan source update directory.');
        }

        $updatedCount = 0;

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $sourcePath = $sourceDir . '/' . $entry;
            $relativePath = trim(substr($sourcePath, strlen($sourceDir)), '/');
            $destPath = $destDir . '/' . $relativePath;

            $updatedCount += $this->syncPath($sourcePath, $destPath, $relativePath);
        }

        return $updatedCount;
    }

    private function syncPath(string $sourcePath, string $destPath, string $relativePath): int
    {
        if ($this->isExcluded($relativePath)) {
            return 0;
        }

        if (is_dir($sourcePath)) {
            $this->ensureDirectory($destPath);
            $entries = scandir($sourcePath);
            if ($entries === false) {
                throw new RuntimeException('Could not scan source directory: ' . $relativePath);
            }

            $updatedCount = 0;
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $childRelative = trim($relativePath . '/' . $entry, '/');
                $updatedCount += $this->syncPath(
                    $sourcePath . '/' . $entry,
                    $destPath . '/' . $entry,
                    $childRelative
                );
            }

            return $updatedCount;
        }

        $this->ensureDirectory(dirname($destPath));

        if (!copy($sourcePath, $destPath)) {
            throw new RuntimeException('Could not write file: ' . $relativePath);
        }

        return 1;
    }

    private function isExcluded(string $relativePath): bool
    {
        $normalized = trim(str_replace('\\', '/', $relativePath), '/');
        if ($normalized === '') {
            return false;
        }

        if (isset($this->excludeMap[$normalized])) {
            return true;
        }

        foreach ($this->excludeMap as $excluded => $_true) {
            if (str_starts_with($normalized, $excluded . '/')) {
                return true;
            }
        }

        return false;
    }

    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!mkdir($path, 0755, true) && !is_dir($path)) {
            throw new RuntimeException('Could not create directory: ' . $path);
        }
    }

    private function deleteRecursively(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        $entries = scandir($path);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $this->deleteRecursively($path . '/' . $entry);
        }

        @rmdir($path);
    }
}
