<?php

namespace App\Entity;

use App\Repository\ReleveRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ReleveRepository::class)
 */
class Releve
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Client::class, inversedBy="releves")
     */
    private $client;

    /**
     * @ORM\Column(type="datetime")
     */
    private $dateReleve;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $ancienIndex;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $nouvelIndex;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateAncienIndex;

    /**
     * @ORM\Column(type="integer")
     */
    private $mois;

    /**
     * @ORM\Column(type="integer")
     */
    private $annee;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $factureDateEdition;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $pu;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $pus;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $limite;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $factureDatePaiement;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $Consomation;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $payer;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="releves")
     */
    private $comptable;

    /**
     * @ORM\ManyToOne(targetEntity=Contract::class, inversedBy="releve")
     */
    private $contract;

    /**
     * @ORM\OneToMany(targetEntity=Facture::class, mappedBy="releve")
     */
    private $factures;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $type;

    public function __construct()
    {
        $this->factures = new ArrayCollection();
    }

    

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function getDateReleve(): ?\DateTimeInterface
    {
        return $this->dateReleve;
    }

    public function setDateReleve(\DateTimeInterface $dateReleve): self
    {
        $this->dateReleve = $dateReleve;

        return $this;
    }

    public function getAncienIndex(): ?string
    {
        return $this->ancienIndex;
    }

    public function setAncienIndex(string $ancienIndex): self
    {
        $this->ancienIndex = $ancienIndex;

        return $this;
    }

    public function getNouvelIndex(): ?string
    {
        return $this->nouvelIndex;
    }

    public function setNouvelIndex(string $nouvelIndex): self
    {
        $this->nouvelIndex = $nouvelIndex;

        return $this;
    }

    public function getDateAncienIndex(): ?\DateTimeInterface
    {
        return $this->dateAncienIndex;
    }

    public function setDateAncienIndex(?\DateTimeInterface $dateAncienIndex): self
    {
        $this->dateAncienIndex = $dateAncienIndex;

        return $this;
    }

    public function getMois(): ?int
    {
        return $this->mois;
    }

    public function setMois(int $mois): self
    {
        $this->mois = $mois;

        return $this;
    }

    public function getAnnee(): ?int
    {
        return $this->annee;
    }

    public function setAnnee(int $annee): self
    {
        $this->annee = $annee;

        return $this;
    }

    public function getFactureDateEdition(): ?\DateTimeInterface
    {
        return $this->factureDateEdition;
    }

    public function setFactureDateEdition(?\DateTimeInterface $factureDateEdition): self
    {
        $this->factureDateEdition = $factureDateEdition;

        return $this;
    }

    public function getPu(): ?string
    {
        return $this->pu;
    }

    public function setPu(string $pu): self
    {
        $this->pu = $pu;

        return $this;
    }

    public function getPus(): ?string
    {
        return $this->pus;
    }

    public function setPus(?string $pus): self
    {
        $this->pus = $pus;

        return $this;
    }

    public function getLimite(): ?string
    {
        return $this->limite;
    }

    public function setLimite(?string $limite): self
    {
        $this->limite = $limite;

        return $this;
    }

    public function getFactureDatePaiement(): ?\DateTimeInterface
    {
        return $this->factureDatePaiement;
    }

    public function setFactureDatePaiement(?\DateTimeInterface $factureDatePaiement): self
    {
        $this->factureDatePaiement = $factureDatePaiement;

        return $this;
    }

    public function getConsomation(): ?string
    {
        return $this->Consomation;
    }

    public function setConsomation(?string $Consomation): self
    {
        $this->Consomation = $Consomation;

        return $this;
    }

    public function isPayer(): ?bool
    {
        return $this->payer;
    }

    public function setPayer(?bool $payer): self
    {
        $this->payer = $payer;

        return $this;
    }

    public function getComptable(): ?User
    {
        return $this->comptable;
    }

    public function setComptable(?User $comptable): self
    {
        $this->comptable = $comptable;

        return $this;
    }

    public function getContract(): ?Contract
    {
        return $this->contract;
    }

    public function setContract(?Contract $contract): self
    {
        $this->contract = $contract;

        return $this;
    }

    /**
     * @return Collection<int, Facture>
     */
    public function getFactures(): Collection
    {
        return $this->factures;
    }

    public function addFacture(Facture $facture): self
    {
        if (!$this->factures->contains($facture)) {
            $this->factures[] = $facture;
            $facture->setReleve($this);
        }

        return $this;
    }

    public function removeFacture(Facture $facture): self
    {
        if ($this->factures->removeElement($facture)) {
            // set the owning side to null (unless already changed)
            if ($facture->getReleve() === $this) {
                $facture->setReleve(null);
            }
        }

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

   
}
