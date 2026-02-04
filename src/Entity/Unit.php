<?php

namespace App\Entity;

use App\Repository\UnitRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UnitRepository::class)]
class Unit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?bool $isGenerating = null;

    #[ORM\ManyToOne(inversedBy: 'units')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Team $team = null;

    #[ORM\ManyToOne(inversedBy: 'units')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Subfondo $subfondo = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'units')]
    private ?self $parent = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    private Collection $units;

    /**
     * @var Collection<int, UnitAssignment>
     */
    #[ORM\OneToMany(targetEntity: UnitAssignment::class, mappedBy: 'unit')]
    private Collection $unitAssignments;

    public function __construct()
    {
        $this->units = new ArrayCollection();
        $this->unitAssignments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function isGenerating(): ?bool
    {
        return $this->isGenerating;
    }

    public function setIsGenerating(bool $isGenerating): static
    {
        $this->isGenerating = $isGenerating;

        return $this;
    }

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): static
    {
        $this->team = $team;

        return $this;
    }

    public function getSubfondo(): ?Subfondo
    {
        return $this->subfondo;
    }

    public function setSubfondo(?Subfondo $subfondo): static
    {
        $this->subfondo = $subfondo;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->units;
    }

    public function addChildren(self $unit): static
    {
        if (!$this->units->contains($unit)) {
            $this->units->add($unit);
            $unit->setParent($this);
        }

        return $this;
    }

    public function removeUnit(self $unit): static
    {
        if ($this->units->removeElement($unit)) {
            // set the owning side to null (unless already changed)
            if ($unit->getParent() === $this) {
                $unit->setParent(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UnitAssignment>
     */
    public function getUnitAssignments(): Collection
    {
        return $this->unitAssignments;
    }

    public function addUnitAssignment(UnitAssignment $unitAssignment): static
    {
        if (!$this->unitAssignments->contains($unitAssignment)) {
            $this->unitAssignments->add($unitAssignment);
            $unitAssignment->setUnit($this);
        }

        return $this;
    }

    public function removeUnitAssignment(UnitAssignment $unitAssignment): static
    {
        if ($this->unitAssignments->removeElement($unitAssignment)) {
            // set the owning side to null (unless already changed)
            if ($unitAssignment->getUnit() === $this) {
                $unitAssignment->setUnit(null);
            }
        }

        return $this;
    }
}
