<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\EventSubscriber;

use App\Event\ConfigureMainMenuEvent;
use App\Utils\MenuItemModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Adds the Expenses page to Kimai's main navigation for authorized users.
 */
final class MenuSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly AuthorizationCheckerInterface $security)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConfigureMainMenuEvent::class => ['onMenuConfigure', 100],
        ];
    }

    public function onMenuConfigure(ConfigureMainMenuEvent $event): void
    {
//    } public function a(): void {
        if (!$this->security->isGranted('view_kimai_expenses_community')) {
            return;
        }

        $event->getMenu()->addChild(
            new MenuItemModel(
                'kimai_expenses_community',
                'Expenses',
                'kimai_expenses_community',
                [],
                'fas fa-receipt'
            )
        );
    }
}
