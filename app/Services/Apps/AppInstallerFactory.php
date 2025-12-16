<?php

namespace App\Services\Apps;

use App\Services\Apps\Installers\WordPressInstaller;
use App\Services\Apps\Installers\JoomlaInstaller;
use App\Services\Apps\Installers\DrupalInstaller;
use App\Services\Apps\Installers\PrestaShopInstaller;
use App\Services\Apps\Installers\PhpBBInstaller;
use App\Services\Apps\Installers\NextcloudInstaller;

class AppInstallerFactory
{
    /**
     * Registered application installers
     *
     * TODO: Complete implementation for all installers
     * Currently only WordPress is fully implemented. Other installers
     * have stub implementations with detailed TODO comments.
     *
     * To complete an installer:
     * 1. Implement the download logic for the application
     * 2. Implement the CLI or web-based installation process
     * 3. Test thoroughly on a staging environment
     * 4. Remove the RuntimeException from the install() method
     */
    protected array $installers = [
        'wordpress' => WordPressInstaller::class,
        'joomla' => JoomlaInstaller::class,
        'drupal' => DrupalInstaller::class,
        'prestashop' => PrestaShopInstaller::class,
        'phpbb' => PhpBBInstaller::class,
        'nextcloud' => NextcloudInstaller::class,
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
