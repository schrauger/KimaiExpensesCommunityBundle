<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\EventSubscriber;

use App\Event\ConfigureMainMenuEvent;
use App\Utils\MenuItemModel;
use KimaiPlugin\KimaiExpensesCommunityBundle\Security\ExpensePermissions;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Adds an Expenses group to Kimai's main navigation, directly below
 * "Time tracking", with My expenses / All expenses / Categories pages.
 */
final class MenuSubscriber implements EventSubscriberInterface
{
    /**
     * Identifiers Kimai may use for the "Time tracking" entry. If the Expenses
     * item still lands in the wrong place, dump the real identifiers with:
     *
     *   array_map(static fn ($c) => $c->getIdentifier(), $event->getMenu()->getChildren())
     */
    private const TIME_TRACKING_IDS = ['times', 'timesheet'];

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
        if (!$this->security->isGranted(ExpensePermissions::VIEW)) {
            return;
        }

        $expenses = new MenuItemModel(
            'kimai_expenses_community',
            'Expenses',
            'kimai_expenses_community',
            [],
            'fas fa-receipt'
        );

        $my = new MenuItemModel(
            'kimai_expenses_community_my',
            'My expenses',
            'kimai_expenses_community',
            [],
            'fas fa-user'
        );
        // Pages that belong to this entry but have no menu item of their own.
        $this->setChildRoutes($my, ['kimai_expenses_community_create', 'kimai_expenses_community_edit']);
        $expenses->addChild($my);

        if ($this->security->isGranted(ExpensePermissions::VIEW_OTHER)) {
            $expenses->addChild(new MenuItemModel(
                'kimai_expenses_community_all',
                'All expenses',
                'kimai_expenses_community_all',
                [],
                'fas fa-users'
            ));
        }

        if ($this->security->isGranted(ExpensePermissions::MANAGE_CATEGORY)) {
            $categories = new MenuItemModel(
                'kimai_expenses_community_category',
                'Categories',
                'kimai_expenses_community_category',
                [],
                'fas fa-tags'
            );
            $this->setChildRoutes($categories, [
                'kimai_expenses_community_category_create',
                'kimai_expenses_community_category_edit',
            ]);
            $expenses->addChild($categories);
        }

        $menu = $event->getMenu();
        $menu->addChild($expenses);

        $this->moveBelowTimeTracking($menu, $expenses);
    }

    /**
     * @param string[] $routes
     */
    private function setChildRoutes(MenuItemModel $item, array $routes): void
    {
        if (method_exists($item, 'setChildRoutes')) {
            $item->setChildRoutes($routes);
        }
    }

    /**
     * addChild() appends, so re-order the children to put $item right after
     * the Time tracking entry. If that entry is not found, $item stays last.
     */
    private function moveBelowTimeTracking(MenuItemModel $menu, MenuItemModel $item): void
    {
        if (!method_exists($menu, 'setChildren')) {
            return;
        }

        $ordered = [];
        $placed = false;

        foreach ($menu->getChildren() as $child) {
            if ($child === $item) {
                continue;
            }

            $ordered[] = $child;

            if (!$placed && \in_array($child->getIdentifier(), self::TIME_TRACKING_IDS, true)) {
                $ordered[] = $item;
                $placed = true;
            }
        }

        if ($placed) {
            $menu->setChildren($ordered);
        }
    }
}
