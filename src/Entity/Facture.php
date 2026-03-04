<?php

namespace App\Entity;

use App\Repository\FactureRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=FactureRepository::class)
 */
class Facture
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    

    /**
     * @ORM\Column(type="datetime")
     */
    private $date;

    /**
     * @ORM\ManyToOne(targetEntity=client::class, inversedBy="factures")
     */
    private $client;

    /**
     * @ORM\ManyToOne(targetEntity=user::class, inversedBy="factures")
     */
    private $caisse;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $montant;

    /**
     * @ORM\Column(type="string", length=255,nullable=true)
     */
    private $payer;

    /**
     * @ORM\Column(type="string", length=255,nullable=true)
     */
    private $reste;

    /**
     * @ORM\ManyToOne(targetEntity=Releve::class, inversedBy="factures")
     */
    private $releve;

   

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getClient(): ?client
    {
        return $this->client;
    }

    public function setClient(?client $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function getCaisse(): ?user
    {
        return $this->caisse;
    }

    public function setCaisse(?user $caisse): self
    {
        $this->caisse = $caisse;

        return $this;
    }

    public function getMontant(): ?string
    {
        return $this->montant;
    }

    public function setMontant(string $montant): self
    {
        $this->montant = $montant;

        return $this;
    }

    public function getPayer(): ?string
    {
        return $this->payer;
    }

    public function setPayer(string $payer): self
    {
        $this->payer = $payer;

        return $this;
    }

    public function getReste(): ?string
    {
        return $this->reste;
    }

    public function setReste(string $reste): self
    {
        $this->reste = $reste;

        return $this;
    }

    public function getReleve(): ?Releve
    {
        return $this->releve;
    }

    public function setReleve(?Releve $releve): self
    {
        $this->releve = $releve;

        return $this;
    }

   }
