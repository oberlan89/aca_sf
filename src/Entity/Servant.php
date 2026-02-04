<?php

namespace App\Entity;

use App\Entity\Enum\Gender;
use App\Repository\ServantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServantRepository::class)]
class Servant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $firstName = null;

    #[ORM\Column(length: 50)]
    private ?string $lastName1 = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $lastName2 = null;

    #[ORM\Column(enumType: Gender::class, nullable: true)]
    private ?Gender $gender = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(nullable: true)]
    private ?int $birthMonth = null;

    #[ORM\Column(nullable: true)]
    private ?int $birthDay = null;

    #[ORM\Column(nullable: true)]
    private ?int $key = null;

    /**
     * @var Collection<int, UnitAssignment>
     */
    #[ORM\OneToMany(targetEntity: UnitAssignment::class, mappedBy: 'servant')]
    private Collection $unitAssignments;

    public function __construct()
    {
        $this->unitAssignments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName1(): ?string
    {
        return $this->lastName1;
    }

    public function setLastName1(string $lastName1): static
    {
        $this->lastName1 = $lastName1;

        return $this;
    }

    public function getLastName2(): ?string
    {
        return $this->lastName2;
    }

    public function setLastName2(?string $lastName2): static
    {
        $this->lastName2 = $lastName2;

        return $this;
    }

    public function getGender(): ?Gender
    {
        return $this->gender;
    }

    public function setGender(Gender $gender): static
    {
        $this->gender = $gender;

        return $this;
    }

    public function getGenderString(): string
    {
        return $this->gender->value ?? '';
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getBirthMonth(): ?int
    {
        return $this->birthMonth;
    }

    public function setBirthMonth(?int $birthMonth): static
    {
        $this->birthMonth = $birthMonth;

        return $this;
    }

    public function getBirthDay(): ?int
    {
        return $this->birthDay;
    }

    public function setBirthDay(?int $birthDay): static
    {
        $this->birthDay = $birthDay;

        return $this;
    }

    public function getKey(): ?int
    {
        return $this->key;
    }

    public function setKey(?int $key): static
    {
        $this->key = $key;

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
            $unitAssignment->setServant($this);
        }

        return $this;
    }

    public function removeUnitAssignment(UnitAssignment $unitAssignment): static
    {
        if ($this->unitAssignments->removeElement($unitAssignment)) {
            // set the owning side to null (unless already changed)
            if ($unitAssignment->getServant() === $this) {
                $unitAssignment->setServant(null);
            }
        }

        return $this;
    }
}
