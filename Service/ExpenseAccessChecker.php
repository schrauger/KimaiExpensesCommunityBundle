<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Service;

use App\Entity\User;
use KimaiPlugin\KimaiExpensesCommunityBundle\Entity\Expense;
use KimaiPlugin\KimaiExpensesCommunityBundle\Security\ExpensePermissions;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Record-level access rules, shared by every controller.
 *
 * Mirrors Kimai's timesheet rules:
 *  - without `view_other_timesheet` a user only sees and changes own expenses
 *  - exported expenses are locked unless the user may edit exported records
 *
 * Action-level permissions (create, edit, delete ...) are checked separately
 * with #[IsGranted]; this class answers "may this user touch THIS record?".
 */
final class ExpenseAccessChecker
{
    public function __construct(private readonly Security $security)
    {
    }

    public function canView(Expense $expense): bool
    {
        return $this->security->isGranted(ExpensePermissions::VIEW_OTHER) || $this->isOwner($expense);
    }

    public function canModify(Expense $expense): bool
    {
        if ($expense->isExported() && !$this->security->isGranted(ExpensePermissions::EDIT_EXPORTED)) {
            return false;
        }

        return $this->canView($expense);
    }

    private function isOwner(Expense $expense): bool
    {
        $user = $this->security->getUser();

        return $user instanceof User && $expense->getUser()->getId() === $user->getId();
    }
}
