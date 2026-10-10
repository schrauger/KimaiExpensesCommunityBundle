<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\KimaiExpensesCommunityBundle\Repository\ExpenseCategoryRepository;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A kind of expense and how it is priced. This is deliberately separate from
 * Kimai's Activity, which describes WORK and carries hourly/fixed rates.
 *
 * Two independent choices describe how an expense of this category is entered:
 *
 *  - Quantity: "ask for a quantity" shows the quantity field by default (miles,
 *    nights, litres ...), labelled with the unit label. Otherwise the quantity
 *    stays in "Extended settings" and is 1.
 *  - Price: either a FIXED rate taken from "default cost" (mileage, a daily
 *    allowance), or ENTERED on each expense (a receipt total, the price of fuel).
 *
 * Examples:
 *  - Restaurant receipt: no quantity, price entered      -> type the receipt total
 *  - Mileage:            quantity (Miles), fixed rate     -> type the miles
 *  - Fuel:               quantity (Litres), price entered -> type litres and price per litre
 *  - Phone allowance:    no quantity, fixed rate          -> nothing to type
 */
#[ORM\Entity(repositoryClass: ExpenseCategoryRepository::class)]
#[ORM\Table(name: 'kimai2_kimai_expenses_community_category')]
class ExpenseCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name = '';

    /**
     * Optional label for the quantity ("Miles", "Nights"). Used as the quantity
     * field's label and shown after quantities in lists. Empty means "Quantity".
     */
    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Length(max: 30)]
    private ?string $unit = null;

    /** The fixed rate per unit, or an optional prefill when the price is entered per expense. */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4)]
    #[Assert\GreaterThanOrEqual(0)]
    private string $defaultCost = '0.0000';

    /** Show the quantity field by default (otherwise it sits in "Extended settings"). */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $askQuantity = false;

    /**
     * The price is typed in on each expense (receipts and other variable amounts)
     * instead of coming from the fixed rate. New categories start as "entered" because
     * most expenses are receipts; existing categories keep their fixed rate.
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $priceEntered = true;

    /** Hidden categories stay on old expenses but cannot be chosen for new ones. */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $visible = true;

    /** Shown under the category field while an expense is being entered. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $helpText = null;

    /** Copied into an empty expense description when the category is chosen. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** Optional "#rrggbb" colour, shown as a dot next to the name in lists. */
    #[ORM\Column(length: 7, nullable: true)]
    #[Assert\Regex(pattern: '/^#[0-9a-fA-F]{6}$/', message: 'Use a hex colour such as #3b82f6.')]
    private ?string $color = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): self
    {
        $unit = $unit !== null ? trim($unit) : null;
        $this->unit = $unit === '' ? null : $unit;

        return $this;
    }

    public function getDefaultCost(): string
    {
        return $this->defaultCost;
    }

    public function setDefaultCost(string|float|int|null $cost): self
    {
        $this->defaultCost = number_format((float) ($cost ?? 0), 4, '.', '');

        return $this;
    }

    public function isAskQuantity(): bool
    {
        return $this->askQuantity;
    }

    public function setAskQuantity(bool $askQuantity): self
    {
        $this->askQuantity = $askQuantity;

        return $this;
    }

    public function isPriceEntered(): bool
    {
        return $this->priceEntered;
    }

    public function setPriceEntered(bool $priceEntered): self
    {
        $this->priceEntered = $priceEntered;

        return $this;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    public function getHelpText(): ?string
    {
        return $this->helpText;
    }

    public function setHelpText(?string $helpText): self
    {
        $this->helpText = $helpText;

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

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): self
    {
        $color = $color !== null ? trim($color) : null;
        $this->color = $color === '' ? null : $color;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
