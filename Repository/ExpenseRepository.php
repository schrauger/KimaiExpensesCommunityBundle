<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\KimaiExpensesCommunityBundle\Entity\Expense;
use KimaiPlugin\KimaiExpensesCommunityBundle\Entity\ExpenseCategory;
use KimaiPlugin\KimaiExpensesCommunityBundle\Query\ExpenseQuery;

/**
 * @extends ServiceEntityRepository<Expense>
 */
final class ExpenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Expense::class);
    }

    /**
     * Every expense matching the filters, newest first (used for the CSV export).
     *
     * @return Expense[]
     */
    public function findByQuery(ExpenseQuery $query, \DateTimeZone $userTimezone): array
    {
        $qb = $this->baseQueryBuilder();
        $this->applyFilters($qb, $query, $userTimezone);
        $this->applyDefaultOrder($qb);

        return $qb->getQuery()->getResult();
    }

    /**
     * One page of matching expenses, newest first. count($paginator) is the
     * number of ALL matching rows, not just this page.
     *
     * @return Paginator<Expense>
     */
    public function paginate(ExpenseQuery $query, \DateTimeZone $userTimezone, int $page, int $perPage): Paginator
    {
        $qb = $this->baseQueryBuilder();
        $this->applyFilters($qb, $query, $userTimezone);
        $this->applyDefaultOrder($qb);
        $qb->setFirstResult(max(0, ($page - 1) * $perPage))->setMaxResults($perPage);

        // Only to-one joins, so there is no need for the slower collection-aware mode.
        return new Paginator($qb->getQuery(), false);
    }

    /**
     * Sum of quantity x cost over ALL matching expenses (not only one page).
     */
    public function sumTotal(ExpenseQuery $query, \DateTimeZone $userTimezone): float
    {
        $qb = $this->baseQueryBuilder();
        $this->applyFilters($qb, $query, $userTimezone);
        // select() replaces the entity selects added by baseQueryBuilder().
        $qb->select('COALESCE(SUM(expense.quantity * expense.cost), 0)');

        return (float) $qb->getQuery()->getSingleScalarResult();
    }

    public function countByCategory(ExpenseCategory $category): int
    {
        return (int) $this->createQueryBuilder('expense')
            ->select('COUNT(expense.id)')
            ->andWhere('expense.category = :category')
            ->setParameter('category', $category)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Create the base query used when retrieving expenses for invoices.
     *
     * Keeping this in the repository gives us one place to evolve the query
     * when we add more invoice filters later.
     */
    public function createInvoiceQueryBuilder(): QueryBuilder
    {
        return $this->baseQueryBuilder();
    }

    /**
     * Apply the overview filters.
     *
     * The From/To filters are calendar dates in the user's timezone. Expense
     * dates are stored as UTC, so each boundary is converted to the UTC instant
     * at which that local day starts. The end date is inclusive (implemented as
     * "< start of the following local day").
     */
    private function applyFilters(QueryBuilder $qb, ExpenseQuery $query, \DateTimeZone $userTimezone): void
    {
        $utc = new \DateTimeZone('UTC');

        if ($query->getBegin() !== null) {
            $begin = (new \DateTimeImmutable($query->getBegin()->format('Y-m-d') . ' 00:00:00', $userTimezone))
                ->setTimezone($utc);
            $qb->andWhere('expense.date >= :rangeBegin')
                ->setParameter('rangeBegin', $begin, Types::DATETIME_IMMUTABLE);
        }

        if ($query->getEnd() !== null) {
            $endExclusive = (new \DateTimeImmutable($query->getEnd()->format('Y-m-d') . ' 00:00:00', $userTimezone))
                ->modify('+1 day')
                ->setTimezone($utc);
            $qb->andWhere('expense.date < :rangeEnd')
                ->setParameter('rangeEnd', $endExclusive, Types::DATETIME_IMMUTABLE);
        }

        if ($query->getCustomer() !== null) {
            // The stored customer can be empty on older rows; fall back to the project's customer.
            $qb->andWhere($qb->expr()->orX(
                'expense.customer = :customer',
                'project.customer = :customer'
            ))->setParameter('customer', $query->getCustomer());
        }

        if ($query->getProject() !== null) {
            $qb->andWhere('expense.project = :project')->setParameter('project', $query->getProject());
        }

        if ($query->getActivity() !== null) {
            $qb->andWhere('expense.activity = :activity')->setParameter('activity', $query->getActivity());
        }

        if ($query->getCategory() !== null) {
            $qb->andWhere('expense.category = :category')->setParameter('category', $query->getCategory());
        }

        if ($query->getUser() !== null) {
            $qb->andWhere('expense.user = :user')->setParameter('user', $query->getUser());
        }

        if ($query->getBillable() !== null) {
            $qb->andWhere('expense.billable = :billable')
                ->setParameter('billable', $query->getBillable() === 'yes');
        }

        if ($query->getExported() !== null) {
            $qb->andWhere('expense.exported = :exported')
                ->setParameter('exported', $query->getExported() === 'yes');
        }

        if ($query->getSearchTerm() !== null) {
            $qb->andWhere($qb->expr()->orX(
                'expense.description LIKE :term',
                'category.name LIKE :term'
            ))->setParameter('term', '%' . addcslashes($query->getSearchTerm(), '%_\\') . '%');
        }
    }

    private function applyDefaultOrder(QueryBuilder $qb): void
    {
        $qb->orderBy('expense.date', 'DESC')->addOrderBy('expense.id', 'DESC');
    }

    /**
     * Expense with all related entities joined and selected. The aliases
     * (category, expenseUser, customer, project, activity) are relied on by
     * the invoice repository and by applyFilters(), so do not rename them.
     */
    private function baseQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('expense')
            ->leftJoin('expense.category', 'category')->addSelect('category')
            ->leftJoin('expense.user', 'expenseUser')->addSelect('expenseUser')
            ->leftJoin('expense.customer', 'customer')->addSelect('customer')
            ->leftJoin('expense.project', 'project')->addSelect('project')
            ->leftJoin('expense.activity', 'activity')->addSelect('activity');
    }
}
