<?php

namespace App\Entity;

use App\Entity\Enum\Assignment;
use App\Repository\UnitAssignmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UnitAssignmentRepository::class)]
class UnitAssignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'unitAssignments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Unit $unit = null;

    #[ORM\ManyToOne(inversedBy: 'unitAssignments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Servant $servant = null;

    #[ORM\Column(enumType: Assignment::class)]
    private ?Assignment $assignment = null;

    #[ORM\Column(length: 255)]
    private ?string $scope = 'SELF';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUnit(): ?Unit
    {
        return $this->unit;
    }

    public function setUnit(?Unit $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function getServant(): ?Servant
    {
        return $this->servant;
    }

    public function setServant(?Servant $servant): static
    {
        $this->servant = $servant;

        return $this;
    }

    public function getAssignment(): ?Assignment
    {
        return $this->assignment;
    }

    public function setAssignment(Assignment $assignment): static
    {
        $this->assignment = $assignment;

        return $this;
    }

    public function getScope(): ?string
    {
        return $this->scope;
    }

    public function setScope(string $scope): static
    {
        $this->scope = $scope;

        return $this;
    }
}
