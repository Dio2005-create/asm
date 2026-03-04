<?php

namespace App\Entity;

use App\Repository\QuartierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=QuartierRepository::class)
 */
class Quartier
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
    private $code;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $nom;

    /**
     * @ORM\OneToMany(targetEntity=Client::class, mappedBy="quartier")
     */
    private $clients;

    /**
     * @ORM\OneToMany(targetEntity=ClientBf::class, mappedBy="quartier")
     */
    private $clientBfs;

    public function __construct()
    {
        $this->clients = new ArrayCollection();
        $this->clientBfs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    /**
     * @return Collection<int, Client>
     */
    public function getClients(): Collection
    {
        return $this->clients;
    }

    public function addClient(Client $client): self
    {
        if (!$this->clients->contains($client)) {
            $this->clients[] = $client;
            $client->setQuartier($this);
        }

        return $this;
    }

    public function removeClient(Client $client): self
    {
        if ($this->clients->removeElement($client)) {
            // set the owning side to null (unless already changed)
            if ($client->getQuartier() === $this) {
                $client->setQuartier(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ClientBf>
     */
    public function getClientBfs(): Collection
    {
        return $this->clientBfs;
    }

    public function addClientBf(ClientBf $clientBf): self
    {
        if (!$this->clientBfs->contains($clientBf)) {
            $this->clientBfs[] = $clientBf;
            $clientBf->setQuartier($this);
        }

        return $this;
    }

    public function removeClientBf(ClientBf $clientBf): self
    {
        if ($this->clientBfs->removeElement($clientBf)) {
            // set the owning side to null (unless already changed)
            if ($clientBf->getQuartier() === $this) {
                $clientBf->setQuartier(null);
            }
        }

        return $this;
    }
}
