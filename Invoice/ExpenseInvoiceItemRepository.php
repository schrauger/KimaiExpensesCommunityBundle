<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Invoice;

use App\Entity\ExportableItem;
use App\Invoice\InvoiceItemRepositoryInterface;
use App\Repository\Query\InvoiceQuery;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\KimaiExpensesCommunityBundle\Entity\Expense;
use KimaiPlugin\KimaiExpensesCommunityBundle\Repository\ExpenseRepository;

/**
 * Supplies community-plugin expenses to Kimai's invoice system.
 *
 * InvoiceItemRepositoryInterface is automatically discovered by Kimai
 * through its AutoconfigureTag attribute, so no manual service tag is needed.
 */
final class ExpenseInvoiceItemRepository implements InvoiceItemRepositoryInterface
{
    public function __construct(
        private readonly ExpenseRepository $expenseRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Find billable expenses matching the current Kimai invoice query.
     *
     * @return ExpenseInvoiceItem[]
     */
    public function getInvoiceItemsForQuery(InvoiceQuery $query): array
    {
        $qb = $this->expenseRepository
            ->createInvoiceQueryBuilder()
            ->andWhere('expense.billable = :billable')
            ->setParameter('billable', true)
            /*
             * An invoice item must have a project because Kimai uses the
             * project's customer when generating invoices.
             */
            ->andWhere('expense.project IS NOT NULL')
            ->orderBy('expense.date', $query->getOrder())
            ->addOrderBy('expense.id', $query->getOrder());

        /*
         * Date range
         */
        if ($query->getBegin() !== null) {
            $qb
                ->andWhere('expense.date >= :expenseBegin')
                ->setParameter('expenseBegin', $this->toUtc($query->getBegin()), Types::DATETIME_IMMUTABLE);
        }

        if ($query->getEnd() !== null) {
            $qb
                ->andWhere('expense.date <= :expenseEnd')
                ->setParameter('expenseEnd', $this->toUtc($query->getEnd()), Types::DATETIME_IMMUTABLE);
        }

        /*
         * Export state.
         *
         * Kimai invoice creation normally requests STATE_NOT_EXPORTED,
         * but honoring STATE_EXPORTED as well keeps the repository compatible
         * with other invoice/query usages.
         */
        if ($query->isNotExported()) {
            $qb
                ->andWhere('expense.exported = :exported')
                ->setParameter('exported', false);
        } elseif ($query->isExported()) {
            $qb
                ->andWhere('expense.exported = :exported')
                ->setParameter('exported', true);
        }

        /*
         * Customer filtering.
         */
        if ($query->hasCustomers()) {
            $customerIds = $query->getCustomerIds();

            if ($customerIds !== []) {
                $qb
                    ->andWhere('project.customer IN (:customerIds)')
                    ->setParameter('customerIds', $customerIds);
            }
        }

        /*
         * Project filtering.
         */
        if ($query->hasProjects()) {
            $projectIds = $query->getProjectIds();

            if ($projectIds !== []) {
                $qb
                    ->andWhere('project.id IN (:projectIds)')
                    ->setParameter('projectIds', $projectIds);
            }
        }

        /*
         * Activity filtering.
         */
        if ($query->hasActivities()) {
            $activityIds = [];

            foreach ($query->getActivities() as $activity) {
                if ($activity->getId() !== null) {
                    $activityIds[] = $activity->getId();
                }
            }

            if ($activityIds !== []) {
                $qb
                    ->andWhere('activity.id IN (:activityIds)')
                    ->setParameter('activityIds', $activityIds);
            }
        }

        /*
         * User filtering.
         */
        if ($query->hasUsers()) {
            $userIds = [];

            foreach ($query->getUsers() as $user) {
                if ($user->getId() !== null) {
                    $userIds[] = $user->getId();
                }
            }

            if ($userIds !== []) {
                $qb
                    ->andWhere('expenseUser.id IN (:userIds)')
                    ->setParameter('userIds', $userIds);
            }
        }

        /** @var Expense[] $expenses */
        $expenses = $qb->getQuery()->getResult();

        /*
         * Must be initialised here. Previously $items only came into existence
         * inside the loop, so when no expense matched (or every match was
         * skipped) the method returned an undefined variable, which broke
         * invoice creation with a 500 error.
         */
        $items = [];

        foreach ($expenses as $expense) {
            if ($expense->getProject() === null) {
                continue;
            }

            /*
             * Skip expenses whose stored customer disagrees with the
             * project's customer.
             */
            if (
                $expense->getCustomer() !== null
                && $expense->getProject()->getCustomer()->getId() !== $expense->getCustomer()->getId()
            ) {
                continue;
            }

            $items[] = new ExpenseInvoiceItem($expense);
        }

        return $items;
    }

    /**
     * Expense dates are stored as UTC, but Kimai's invoice query carries the
     * range in the user's timezone. Binding such a date as-is would compare local
     * clock digits with UTC digits and drop expenses near the range edges (for
     * example the last hours of the month), so convert to the same instant in UTC.
     */
    private function toUtc(\DateTimeInterface $date): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($date)->setTimezone(new \DateTimeZone('UTC'));
    }

    /**
     * Mark all expenses that participated in an invoice as exported.
     *
     * Kimai passes every invoice entry to every registered invoice-item
     * repository, so only process our own adapter type.
     *
     * @param ExportableItem[] $invoiceItems
     */
    public function setExported(array $invoiceItems): void
    {
        $changed = false;

        foreach ($invoiceItems as $invoiceItem) {
            if (!$invoiceItem instanceof ExpenseInvoiceItem) {
                continue;
            }

            $expense = $invoiceItem->getExpense();

            if (!$expense->isExported()) {
                $expense->setExported(true);
                $changed = true;
            }
        }

        if ($changed) {
            $this->entityManager->flush();
        }
    }
}
