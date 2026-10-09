<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Query;

use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\User;
use KimaiPlugin\KimaiExpensesCommunityBundle\Entity\ExpenseCategory;

/**
 * Filter state for the expense overview, populated from the toolbar form.
 *
 * begin/end are plain calendar dates; the repository interprets them in the
 * current user's timezone.
 */
final class ExpenseQuery
{
    private ?\DateTimeInterface $begin = null;
    private ?\DateTimeInterface $end = null;
    private ?Customer $customer = null;
    private ?Project $project = null;
    private ?Activity $activity = null;
    private ?ExpenseCategory $category = null;
    private ?User $user = null;
    /** 'yes' | 'no' | null (all) */
    private ?string $billable = null;
    /** 'yes' | 'no' | null (all) */
    private ?string $exported = null;
    private ?string $searchTerm = null;

    public function getBegin(): ?\DateTimeInterface
    {
        return $this->begin;
    }

    public function setBegin(?\DateTimeInterface $begin): void
    {
        $this->begin = $begin;
    }

    public function getEnd(): ?\DateTimeInterface
    {
        return $this->end;
    }

    public function setEnd(?\DateTimeInterface $end): void
    {
        $this->end = $end;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): void
    {
        $this->project = $project;
    }

    public function getActivity(): ?Activity
    {
        return $this->activity;
    }

    public function setActivity(?Activity $activity): void
    {
        $this->activity = $activity;
    }

    public function getCategory(): ?ExpenseCategory
    {
        return $this->category;
    }

    public function setCategory(?ExpenseCategory $category): void
    {
        $this->category = $category;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
    }

    public function getBillable(): ?string
    {
        return $this->billable;
    }

    public function setBillable(?string $billable): void
    {
        $this->billable = $billable;
    }

    public function getExported(): ?string
    {
        return $this->exported;
    }

    public function setExported(?string $exported): void
    {
        $this->exported = $exported;
    }

    public function getSearchTerm(): ?string
    {
        return $this->searchTerm;
    }

    public function setSearchTerm(?string $searchTerm): void
    {
        $searchTerm = $searchTerm !== null ? trim($searchTerm) : null;
        $this->searchTerm = $searchTerm === '' ? null : $searchTerm;
    }

    /**
     * Number of active filters, shown as a badge on the filter button.
     * The user filter only counts where the user can choose it ("All expenses").
     */
    public function countFilter(bool $includeUser = false): int
    {
        $filters = [
            $this->begin, $this->end, $this->customer, $this->project, $this->activity,
            $this->category, $this->billable, $this->exported, $this->searchTerm,
        ];

        if ($includeUser) {
            $filters[] = $this->user;
        }

        return \count(array_filter($filters, static fn ($value): bool => $value !== null));
    }
}
