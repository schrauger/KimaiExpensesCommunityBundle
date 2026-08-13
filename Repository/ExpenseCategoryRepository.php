<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\KimaiExpensesCommunityBundle\Entity\ExpenseCategory;

final class ExpenseCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExpenseCategory::class);
    }

    /** @return ExpenseCategory[] */
    public function findVisible(): array
    {
        return $this->createQueryBuilder('category')
            ->andWhere('category.visible = :visible')
            ->setParameter('visible', true)
            ->orderBy('category.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
