<?php

namespace App\Entity;

use App\Repository\PaymentTrancheRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PaymentTrancheRepository::class)
 */
class PaymentTranche
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $argent;

    /**
     * @ORM\ManyToOne(targetEntity=Contract::class, inversedBy="paymentTranches")
     */
    private $contrat;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="paymentTranches")
     */
    private $caisse;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArgent(): ?string
    {
        return $this->argent;
    }

    public function setArgent(string $argent): self
    {
        $this->argent = $argent;

        return $this;
    }

    public function getContrat(): ?Contract
    {
        return $this->contrat;
    }

    public function setContrat(?Contract $contrat): self
    {
        $this->contrat = $contrat;

        return $this;
    }

    public function getCaisse(): ?User
    {
        return $this->caisse;
    }

    public function setCaisse(?User $caisse): self
    {
        $this->caisse = $caisse;

        return $this;
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
}
