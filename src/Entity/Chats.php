<?php

namespace App\Entity;

use App\Repository\ChatsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChatsRepository::class)]
class Chats
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private ?string $chatToken = null;

    #[ORM\Column(length: 20)]
    private ?string $tipo = null;

    #[ORM\Column]
    private ?bool $temporal = null;

    #[ORM\Column]
    private ?\DateTime $fechaCreacion = null;

    /**
     * @var Collection<int, ChatUsuarios>
     */
    #[ORM\OneToMany(targetEntity: ChatUsuarios::class, mappedBy: 'chat')]
    private Collection $chatUsuarios;

    /**
     * @var Collection<int, Mensajes>
     */
    #[ORM\OneToMany(targetEntity: Mensajes::class, mappedBy: 'chat')]
    private Collection $mensajes;

    public function __construct()
    {
        $this->chatUsuarios = new ArrayCollection();
        $this->mensajes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChatToken(): ?string
    {
        return $this->chatToken;
    }

    public function setChatToken(string $chatToken): static
    {
        $this->chatToken = $chatToken;

        return $this;
    }

    public function getTipo(): ?string
    {
        return $this->tipo;
    }

    public function setTipo(string $tipo): static
    {
        $this->tipo = $tipo;

        return $this;
    }

    public function isTemporal(): ?bool
    {
        return $this->temporal;
    }

    public function setTemporal(bool $temporal): static
    {
        $this->temporal = $temporal;

        return $this;
    }

    public function getFechaCreacion(): ?\DateTime
    {
        return $this->fechaCreacion;
    }

    public function setFechaCreacion(\DateTime $fechaCreacion): static
    {
        $this->fechaCreacion = $fechaCreacion;

        return $this;
    }

    /**
     * @return Collection<int, ChatUsuarios>
     */
    public function getChatUsuarios(): Collection
    {
        return $this->chatUsuarios;
    }

    public function addChatUsuario(ChatUsuarios $chatUsuario): static
    {
        if (!$this->chatUsuarios->contains($chatUsuario)) {
            $this->chatUsuarios->add($chatUsuario);
            $chatUsuario->setChat($this);
        }

        return $this;
    }

    public function removeChatUsuario(ChatUsuarios $chatUsuario): static
    {
        if ($this->chatUsuarios->removeElement($chatUsuario)) {
            // set the owning side to null (unless already changed)
            if ($chatUsuario->getChat() === $this) {
                $chatUsuario->setChat(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Mensajes>
     */
    public function getMensajes(): Collection
    {
        return $this->mensajes;
    }

    public function addMensaje(Mensajes $mensaje): static
    {
        if (!$this->mensajes->contains($mensaje)) {
            $this->mensajes->add($mensaje);
            $mensaje->setChat($this);
        }

        return $this;
    }

    public function removeMensaje(Mensajes $mensaje): static
    {
        if ($this->mensajes->removeElement($mensaje)) {
            // set the owning side to null (unless already changed)
            if ($mensaje->getChat() === $this) {
                $mensaje->setChat(null);
            }
        }

        return $this;
    }
}
