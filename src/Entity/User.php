<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Entity(repositoryClass=UserRepository::class)
 * @ORM\Table(name="`user`")
 */
class User implements UserInterface
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
    private $username;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $password;

    /**
     * @ORM\Column(type="json")
     */
    private $roles = [];

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $fullName;

    /**
     * @ORM\OneToMany(targetEntity=Releve::class, mappedBy="comptable")
     */
    private $releves;

    /**
     * @ORM\OneToMany(targetEntity=Facture::class, mappedBy="caisse")
     */
    private $factures;

    /**
     * @ORM\OneToMany(targetEntity=PaymentTranche::class, mappedBy="caisse")
     */
    private $paymentTranches;

    public function __construct()
    {
        $this->releves = new ArrayCollection();
        $this->factures = new ArrayCollection();
        $this->paymentTranches = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getRoles(): ?array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(?string $fullName): self
    {
        $this->fullName = $fullName;

        return $this;
    }
    /**
     * @see UserInterface
     */
    public function getSalt()
    {
        // not needed when using the "bcrypt" algorithm in security.yaml
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    /**
     * @return Collection<int, Releve>
     */
    public function getReleves(): Collection
    {
        return $this->releves;
    }

    public function addRelefe(Releve $relefe): self
    {
        if (!$this->releves->contains($relefe)) {
            $this->releves[] = $relefe;
            $relefe->setComptable($this);
        }

        return $this;
    }

    public function removeRelefe(Releve $relefe): self
    {
        if ($this->releves->removeElement($relefe)) {
            // set the owning side to null (unless already changed)
            if ($relefe->getComptable() === $this) {
                $relefe->setComptable(null);
            }
        }

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
            $facture->setCaisse($this);
        }

        return $this;
    }

    public function removeFacture(Facture $facture): self
    {
        if ($this->factures->removeElement($facture)) {
            // set the owning side to null (unless already changed)
            if ($facture->getCaisse() === $this) {
                $facture->setCaisse(null);
            }
        }

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
            $paymentTranch->setCaisse($this);
        }

        return $this;
    }

    public function removePaymentTranch(PaymentTranche $paymentTranch): self
    {
        if ($this->paymentTranches->removeElement($paymentTranch)) {
            // set the owning side to null (unless already changed)
            if ($paymentTranch->getCaisse() === $this) {
                $paymentTranch->setCaisse(null);
            }
        }

        return $this;
    }

    
}
