<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Security;

/**
 * Permission names used by this plugin.
 *
 * Kept in one place so controllers, the menu and the permission registration
 * cannot drift apart because of a typo. Twig templates cannot use these
 * constants directly, so they repeat the string names in `is_granted()`.
 */
final class ExpensePermissions
{
    public const VIEW = 'view_kimai_expenses_community';
    public const CREATE = 'create_kimai_expenses_community';
    public const EDIT = 'edit_kimai_expenses_community';
    public const DELETE = 'delete_kimai_expenses_community';

    /** Change the cost per unit of a single expense (otherwise the category default is used). */
    public const EDIT_COST = 'edit_kimai_expenses_community_cost';

    /** Change the exported flag (like Kimai's edit_export_own_timesheet). */
    public const EDIT_EXPORT = 'edit_export_kimai_expenses_community';

    /** Edit or delete expenses that are already exported (they are locked otherwise). */
    public const EDIT_EXPORTED = 'edit_exported_kimai_expenses_community';

    /** Download the filtered expense list as CSV. */
    public const EXPORT = 'export_kimai_expenses_community';

    public const MANAGE_CATEGORY = 'manage_kimai_expenses_community_category';

    /** Kimai core permission: see other users' timesheets (and, here, expenses). */
    public const VIEW_OTHER = 'view_other_timesheet';

    /**
     * Every permission this plugin registers (not the Kimai core one).
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::VIEW,
            self::CREATE,
            self::EDIT,
            self::DELETE,
            self::EDIT_COST,
            self::EDIT_EXPORT,
            self::EDIT_EXPORTED,
            self::EXPORT,
            self::MANAGE_CATEGORY,
        ];
    }
}
