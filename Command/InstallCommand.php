<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Command;

use App\Command\AbstractBundleInstallerCommand;

/**
 * Provides `bin/console kimai:bundle:kimai-expenses-community:install` for installing
 * and, later, upgrading this plugin's Doctrine migrations.
 */
final class InstallCommand extends AbstractBundleInstallerCommand
{
    protected function getBundleCommandNamePart(): string
    {
        return 'kimai-expenses-community';
    }

    protected function getMigrationConfigFilename(): ?string
    {
        return __DIR__ . '/../Migrations/doctrine_migrations.yaml';
    }
}
