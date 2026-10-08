<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\KimaiExpensesCommunityBundle\Repository\ExpenseCategoryRepository;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A price list entry for expenses: a unit (mile, night, item ...) and a default
 * cost per unit. This is deliberately separate from Kimai's Activity, which
 * describes WORK and has hourly/fixed rates; a category describes a PURCHASE or
 * allowance with a per-unit price, help text and a default description.
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

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    private string $unit = 'item';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4)]
    #[Assert\GreaterThanOrEqual(0)]
    private string $defaultCost = '1.0000';

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

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function setUnit(string $unit): self
    {
        $this->unit = trim($unit);

        return $this;
    }

    public function getDefaultCost(): string
    {
        return $this->defaultCost;
    }

    public function setDefaultCost(string|float|int $cost): self
    {
        $this->defaultCost = number_format((float) $cost, 4, '.', '');

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
