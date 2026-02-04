<?php

namespace App\Entity;

use App\Repository\ChatUsuariosRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChatUsuariosRepository::class)]
class ChatUsuarios
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTime $fechaUnion = null;

    #[ORM\ManyToOne(inversedBy: 'chatUsuarios')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Chats $chat = null;

    #[ORM\ManyToOne(inversedBy: 'chatUsuarios')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $usuario = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFechaUnion(): ?\DateTime
    {
        return $this->fechaUnion;
    }

    public function setFechaUnion(\DateTime $fechaUnion): static
    {
        $this->fechaUnion = $fechaUnion;

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
