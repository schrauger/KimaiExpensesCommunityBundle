<?php

declare(strict_types=1);

namespace KimaiPlugin\MileageExpenseBundle\Command;

use App\Command\AbstractBundleInstallerCommand;

/**
 * Provides `bin/console kimai:bundle:mileage-expense:install` for installing
 * and, later, upgrading this plugin's Doctrine migrations.
 */
final class InstallCommand extends AbstractBundleInstallerCommand
{
    protected function getBundleCommandNamePart(): string
    {
        return 'mileage-expense';
    }

    protected function getMigrationConfigFilename(): ?string
    {
        return __DIR__ . '/../Migrations/doctrine_migrations.yaml';
    }
}
