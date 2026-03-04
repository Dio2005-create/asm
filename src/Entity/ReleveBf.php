<?php

namespace App\Entity;

use App\Repository\ReleveBfRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ReleveBfRepository::class)
 */
class ReleveBf
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=ClientBf::class, inversedBy="releveBfs")
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
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $factureDatePaiement;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $pu;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $pu2;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $limite;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $consomation;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): ?ClientBf
    {
        return $this->client;
    }

    public function setClient(?ClientBf $client): self
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

    public function getFactureDatePaiement(): ?\DateTimeInterface
    {
        return $this->factureDatePaiement;
    }

    public function setFactureDatePaiement(?\DateTimeInterface $factureDatePaiement): self
    {
        $this->factureDatePaiement = $factureDatePaiement;

        return $this;
    }

    public function getPu(): ?int
    {
        return $this->pu;
    }

    public function setPu(?int $pu): self
    {
        $this->pu = $pu;

        return $this;
    }

    public function getPu2(): ?string
    {
        return $this->pu2;
    }

    public function setPu2(?string $pu2): self
    {
        $this->pu2 = $pu2;

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

    public function getConsomation(): ?string
    {
        return $this->consomation;
    }

    public function setConsomation(?string $consomation): self
    {
        $this->consomation = $consomation;

        return $this;
    }
}
