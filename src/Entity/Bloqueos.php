<?php

namespace App\Entity;

use App\Repository\BloqueosRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BloqueosRepository::class)]
class Bloqueos
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTime $fechaBloqueo = null;

    #[ORM\ManyToOne(inversedBy: 'bloqueosRealizados')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $usuarioBloqueador = null;

    #[ORM\ManyToOne(inversedBy: 'bloqueosRecibidos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $usuarioBloqueado = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFechaBloqueo(): ?\DateTime
    {
        return $this->fechaBloqueo;
    }

    public function setFechaBloqueo(\DateTime $fechaBloqueo): static
    {
        $this->fechaBloqueo = $fechaBloqueo;

        return $this;
    }

    public function getUsuarioBloqueador(): ?User
    {
        return $this->usuarioBloqueador;
    }

    public function setUsuarioBloqueador(?User $usuarioBloqueador): static
    {
        $this->usuarioBloqueador = $usuarioBloqueador;

        return $this;
    }

    public function getUsuarioBloqueado(): ?User
    {
        return $this->usuarioBloqueado;
    }

    public function setUsuarioBloqueado(?User $usuarioBloqueado): static
    {
        $this->usuarioBloqueado = $usuarioBloqueado;

        return $this;
    }
}
