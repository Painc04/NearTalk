<?php

namespace App\Entity;

use App\Repository\MensajesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MensajesRepository::class)]
class Mensajes
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $mensaje = null;

    #[ORM\Column]
    private ?\DateTime $fechaEnvio = null;

    #[ORM\Column]
    private ?bool $esSistema = null;

    #[ORM\ManyToOne(inversedBy: 'mensajes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Chats $chat = null;

    #[ORM\ManyToOne(inversedBy: 'mensajes')]
    private ?User $usuario = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMensaje(): ?string
    {
        return $this->mensaje;
    }

    public function setMensaje(string $mensaje): static
    {
        $this->mensaje = $mensaje;

        return $this;
    }

    public function getFechaEnvio(): ?\DateTime
    {
        return $this->fechaEnvio;
    }

    public function setFechaEnvio(\DateTime $fechaEnvio): static
    {
        $this->fechaEnvio = $fechaEnvio;

        return $this;
    }

    public function isEsSistema(): ?bool
    {
        return $this->esSistema;
    }

    public function setEsSistema(bool $esSistema): static
    {
        $this->esSistema = $esSistema;

        return $this;
    }

    public function getChat(): ?Chats
    {
        return $this->chat;
    }

    public function setChat(?Chats $chat): static
    {
        $this->chat = $chat;

        return $this;
    }

    public function getUsuario(): ?User
    {
        return $this->usuario;
    }

    public function setUsuario(?User $usuario): static
    {
        $this->usuario = $usuario;

        return $this;
    }
}
