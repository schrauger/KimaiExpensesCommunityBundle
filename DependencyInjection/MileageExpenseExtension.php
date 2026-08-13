<?php

declare(strict_types=1);

namespace KimaiPlugin\MileageExpenseBundle\DependencyInjection;

use App\Plugin\AbstractPluginExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;

/**
 * Registers this bundle's services and plugin-specific Kimai configuration.
 */
final class MileageExpenseExtension extends AbstractPluginExtension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new Loader\YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.yaml');
    }

    public function prepend(ContainerBuilder $container): void
    {
        // Kimai discovers plugin permissions from the merged configuration.
        // Grant the full set to super-admins; other roles can be configured in
        // System > Roles after the plugin is installed.
        $container->prependExtensionConfig('kimai', [
            'permissions' => [
                'roles' => [
                    'ROLE_SUPER_ADMIN' => [
                        'view_mileage_expense',
                        'create_mileage_expense',
                        'edit_mileage_expense',
                        'delete_mileage_expense',
                        'edit_mileage_expense_cost',
                        'manage_mileage_expense_category',
                        'edit_exported_mileage_expense',
                    ],
                ],
            ],
        ]);
    }
}
