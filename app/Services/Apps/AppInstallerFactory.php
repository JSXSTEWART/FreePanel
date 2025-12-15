<?php

namespace App\Services\Apps;

use App\Services\Apps\Installers\WordPressInstaller;

class AppInstallerFactory
{
    protected array $installers = [
        'wordpress' => WordPressInstaller::class,
    ];

    /**
     * Create an installer for the given application type
     */
    public function create(string $appType): AppInstallerInterface
    {
        $appType = strtolower($appType);

        if (!isset($this->installers[$appType])) {
            throw new \InvalidArgumentException("Unknown application type: {$appType}");
        }

        $installerClass = $this->installers[$appType];

        return app($installerClass);
    }

    /**
     * Register an installer for an application type
     */
    public function register(string $appType, string $installerClass): void
    {
        $this->installers[strtolower($appType)] = $installerClass;
    }

    /**
     * Get all registered application types
     */
    public function getRegisteredTypes(): array
    {
        return array_keys($this->installers);
    }
}
