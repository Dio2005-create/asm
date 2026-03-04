<?php

namespace App\Entity;

use App\Repository\ClientBfRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ClientBfRepository::class)
 */
class ClientBf
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $nom;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $prenom;

    /**
     * @ORM\ManyToOne(targetEntity=Quartier::class, inversedBy="clientBfs")
     */
    private $quartier;

    private $isSpecific = false;

    private $audit = false;

    /**
     * @ORM\OneToMany(targetEntity=ReleveBf::class, mappedBy="client")
     */
    private $releveBfs;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $code;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $adresse;

    public function __construct()
    {
        $this->releveBfs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIsSpecific(): ?bool
    {
        return $this->isSpecific;
    }

    public function setIsSpecific(bool $isSpecific): self
    {
        $this->isSpecific = $isSpecific;

        return $this;
    }

    
	public function getAudit(): ?bool
{
    return $this->audit;
}

// Méthode setter
public function setAudit(?bool $audit): self
{
    $this->audit = $audit;
    return $this;
}

    

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): self
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getQuartier(): ?Quartier
    {
        return $this->quartier;
    }

    public function setQuartier(?Quartier $quartier): self
    {
        $this->quartier = $quartier;

        return $this;
    }

    /**
     * @return Collection<int, ReleveBf>
     */
    public function getReleveBfs(): Collection
    {
        return $this->releveBfs;
    }

    public function addReleveBf(ReleveBf $releveBf): self
    {
        if (!$this->releveBfs->contains($releveBf)) {
            $this->releveBfs[] = $releveBf;
            $releveBf->setClient($this);
        }

        return $this;
    }

    public function removeReleveBf(ReleveBf $releveBf): self
    {
        if ($this->releveBfs->removeElement($releveBf)) {
            // set the owning side to null (unless already changed)
            if ($releveBf->getClient() === $this) {
                $releveBf->setClient(null);
            }
        }

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): self
    {
        $this->adresse = $adresse;

        return $this;
    }
}
