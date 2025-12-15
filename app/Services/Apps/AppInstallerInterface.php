<?php

namespace App\Services\Apps;

interface AppInstallerInterface
{
    /**
     * Install the application
     */
    public function install(array $options): array;

    /**
     * Update the application
     */
    public function update(string $path, array $options): array;

    /**
     * Uninstall the application
     */
    public function uninstall(string $path, array $options): void;

    /**
     * Create a backup of the application
     */
    public function backup(string $path, string $backupPath): void;

    /**
     * Get the installed version
     */
    public function getInstalledVersion(string $path): ?string;

    /**
     * Clone to staging environment
     */
    public function cloneToStaging(string $productionPath, string $stagingPath, array $options): array;
}
