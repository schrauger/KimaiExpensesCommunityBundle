<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\KimaiExpensesCommunityBundle\Repository\ExpenseCategoryRepository;
use Symfony\Component\Validator\Constraints as Assert;

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

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $visible = true;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $helpText = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

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

    public function __toString(): string
    {
        return $this->name;
    }
}
