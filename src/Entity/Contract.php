<?php

namespace App\Entity;

use App\Repository\ContractRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ContractRepository::class)
 */
class Contract
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
    private $totalAMount;

    /**
     * @ORM\OneToMany(targetEntity=Releve::class, mappedBy="contract")
     */
    private $releve;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $countTranche;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $startDate;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $endDate;

    /**
     * @ORM\Column(type="string", length=255,nullable=true)
     */
    private $status;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $reste;

    /**
     * @ORM\ManyToOne(targetEntity=Client::class, inversedBy="contracts")
     */
    private $client;

    /**
     * @ORM\OneToMany(targetEntity=PaymentTranche::class, mappedBy="contrat")
     */
    private $paymentTranches;

    public function __construct()
    {
        $this->releve = new ArrayCollection();
        $this->paymentTranches = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTotalAMount(): ?string
    {
        return $this->totalAMount;
    }

    public function setTotalAMount(string $totalAMount): self
    {
        $this->totalAMount = $totalAMount;

        return $this;
    }

    /**
     * @return Collection<int, Releve>
     */
    public function getReleve(): Collection
    {
        return $this->releve;
    }

    public function addReleve(Releve $releve): self
    {
        if (!$this->releve->contains($releve)) {
            $this->releve[] = $releve;
            $releve->setContract($this);
        }

        return $this;
    }

    public function removeReleve(Releve $releve): self
    {
        if ($this->releve->removeElement($releve)) {
            // set the owning side to null (unless already changed)
            if ($releve->getContract() === $this) {
                $releve->setContract(null);
            }
        }

        return $this;
    }

    public function getCountTranche(): ?string
    {
        return $this->countTranche;
    }

    public function setCountTranche(?string $countTranche): self
    {
        $this->countTranche = $countTranche;

        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getReste(): ?string
    {
        return $this->reste;
    }

    public function setReste(?string $reste): self
    {
        $this->reste = $reste;

        return $this;
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

    /**
     * @return Collection<int, PaymentTranche>
     */
    public function getPaymentTranches(): Collection
    {
        return $this->paymentTranches;
    }

    public function addPaymentTranch(PaymentTranche $paymentTranch): self
    {
        if (!$this->paymentTranches->contains($paymentTranch)) {
            $this->paymentTranches[] = $paymentTranch;
            $paymentTranch->setContrat($this);
        }

        return $this;
    }

    public function removePaymentTranch(PaymentTranche $paymentTranch): self
    {
        if ($this->paymentTranches->removeElement($paymentTranch)) {
            // set the owning side to null (unless already changed)
            if ($paymentTranch->getContrat() === $this) {
                $paymentTranch->setContrat(null);
            }
        }

        return $this;
    }
}
