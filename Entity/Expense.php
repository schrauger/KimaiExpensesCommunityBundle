<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Entity;

use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\KimaiExpensesCommunityBundle\Repository\ExpenseRepository;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * One recorded expense: quantity x cost per unit, owned by a user and
 * optionally tied to a customer, project and activity.
 *
 * Dates are stored in UTC. Doctrine reads a DATETIME column back in PHP's
 * default timezone, so getDate() re-labels the stored digits as UTC again.
 */
#[ORM\Entity(repositoryClass: ExpenseRepository::class)]
#[ORM\Table(name: 'kimai2_kimai_expenses_community')]
class Expense
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull]
    private \DateTimeInterface $date;

    #[ORM\ManyToOne(targetEntity: ExpenseCategory::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ExpenseCategory $category;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Customer $customer = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Activity $activity = null;

    /** A multiplier for the cost. Negative values are allowed (credits, corrections). */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4)]
    #[Assert\NotBlank]
    private string $quantity = '1.0000';

    /**
     * Cost per unit. Copied from the selected category when the category is set,
     * so a later change to the category does not rewrite historical expenses.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4)]
    #[Assert\GreaterThanOrEqual(0)]
    private string $cost = '1.0000';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $billable = true;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $exported = false;

    /** Random name of the stored receipt file (see ReceiptStorage). */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $receiptFilename = null;

    /** Name shown to users and used for downloads. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $receiptOriginalName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $receiptMimeType = null;

    public function __construct()
    {
        $this->date = new \DateTime('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * The column holds UTC. Doctrine reads it back in PHP's default timezone,
     * so re-label the digits as UTC to get the correct instant.
     */
    public function getDate(): \DateTimeInterface
    {
        return new \DateTime($this->date->format('Y-m-d H:i:s'), new \DateTimeZone('UTC'));
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = \DateTime::createFromInterface($date)->setTimezone(new \DateTimeZone('UTC'));

        return $this;
    }

    public function getCategory(): ExpenseCategory
    {
        return $this->category;
    }

    public function setCategory(ExpenseCategory $category): self
    {
        $this->category = $category;
        $this->cost = $category->getDefaultCost();

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    /**
     * Choosing a project also fixes the customer: a project belongs to exactly
     * one customer, so the stored customer can never disagree with it. (The
     * form submits "customer" first, so the project always wins.) Clearing the
     * project leaves the customer alone.
     */
    public function setProject(?Project $project): self
    {
        $this->project = $project;

        if ($project !== null) {
            $this->customer = $project->getCustomer();
        }

        return $this;
    }

    public function getActivity(): ?Activity
    {
        return $this->activity;
    }

    public function setActivity(?Activity $activity): self
    {
        $this->activity = $activity;

        return $this;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function setQuantity(string|float|int|null $quantity): self
    {
        // An empty quantity means "one" (the quantity is optional for many categories).
        $this->quantity = number_format((float) ($quantity ?? 1), 4, '.', '');

        return $this;
    }

    public function getCost(): string
    {
        return $this->cost;
    }

    public function setCost(string|float|int|null $cost): self
    {
        $this->cost = number_format((float) ($cost ?? 0), 4, '.', '');

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function isBillable(): bool
    {
        return $this->billable;
    }

    public function setBillable(bool $billable): self
    {
        $this->billable = $billable;

        return $this;
    }

    public function isExported(): bool
    {
        return $this->exported;
    }

    public function setExported(bool $exported): self
    {
        $this->exported = $exported;

        return $this;
    }

    public function hasReceipt(): bool
    {
        return $this->receiptFilename !== null;
    }

    public function getReceiptFilename(): ?string
    {
        return $this->receiptFilename;
    }

    public function getReceiptOriginalName(): ?string
    {
        return $this->receiptOriginalName;
    }

    public function getReceiptMimeType(): ?string
    {
        return $this->receiptMimeType;
    }

    public function setReceipt(string $filename, string $originalName, string $mimeType): self
    {
        $this->receiptFilename = $filename;
        $this->receiptOriginalName = $originalName;
        $this->receiptMimeType = $mimeType;

        return $this;
    }

    public function clearReceipt(): self
    {
        $this->receiptFilename = null;
        $this->receiptOriginalName = null;
        $this->receiptMimeType = null;

        return $this;
    }

    /**
     * A new, unsaved copy for "Create copy": same category, quantity, rate,
     * links, description and billable flag, but dated now, never exported, and
     * without a receipt (the file itself is not duplicated).
     */
    public function duplicateFor(User $owner): self
    {
        $copy = new self();
        $copy->user = $owner;
        $copy->category = $this->category;
        $copy->cost = $this->cost;
        $copy->quantity = $this->quantity;
        $copy->description = $this->description;
        $copy->billable = $this->billable;
        $copy->customer = $this->customer;
        $copy->project = $this->project;
        $copy->activity = $this->activity;

        return $copy;
    }

    /**
     * quantity x cost, e.g. 47.5 miles x 0.70 = 33.25. Used for display and as
     * the invoice line amount; Kimai rounds money when it renders the invoice.
     */
    public function getTotal(): float
    {
        return (float) $this->quantity * (float) $this->cost;
    }

    /**
     * Categories whose price is typed in per expense (receipts ...) need a real amount.
     */
    #[Assert\Callback]
    public function validateAmount(ExecutionContextInterface $context): void
    {
        if (isset($this->category) && $this->category->isPriceEntered() && (float) $this->cost <= 0) {
            $context->buildViolation('Enter the amount.')
                ->atPath('cost')
                ->addViolation();
        }
    }

    /**
     * Invoices only pick up expenses that have a project (Kimai takes the
     * customer from the project), so a billable expense without one would be
     * silently skipped. Ask for the project up front instead.
     */
    #[Assert\Callback]
    public function validateInvoiceable(ExecutionContextInterface $context): void
    {
        if ($this->billable && $this->project === null) {
            $context->buildViolation('A billable expense needs a project, otherwise it cannot appear on an invoice. Choose a project or untick "Billable".')
                ->atPath('project')
                ->addViolation();
        }
    }
}
