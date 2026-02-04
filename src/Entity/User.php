<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    private ?string $username = null;

    #[ORM\Column]
    private ?float $latitud = null;

    #[ORM\Column]
    private ?float $longitud = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $ultimaConexion = null;

    #[ORM\Column]
    private ?bool $enLinea = null;



    /**
     * @var Collection<int, Bloqueos>
     */
    #[ORM\OneToMany(targetEntity: Bloqueos::class, mappedBy: 'usuario')]
    private Collection $bloqueo;

    /**
     * @var Collection<int, Bloqueos>
     */
    #[ORM\OneToMany(targetEntity: Bloqueos::class, mappedBy: 'usuario')]
    private Collection $bloqueos;

    #[ORM\Column(length: 64)]
    private ?string $userToken = null;

    /**
     * @var Collection<int, Bloqueos>
     */
    #[ORM\OneToMany(targetEntity: Bloqueos::class, mappedBy: 'usuarioBloqueador')]
    private Collection $bloqueosRealizados;

    /**
     * @var Collection<int, Bloqueos>
     */
    #[ORM\OneToMany(targetEntity: Bloqueos::class, mappedBy: 'usuarioBloqueado')]
    private Collection $bloqueosRecibidos;

    /**
     * @var Collection<int, ChatUsuarios>
     */
    #[ORM\OneToMany(targetEntity: ChatUsuarios::class, mappedBy: 'usuario')]
    private Collection $chatUsuarios;

    /**
     * @var Collection<int, Mensajes>
     */
    #[ORM\OneToMany(targetEntity: Mensajes::class, mappedBy: 'usuario')]
    private Collection $mensajes;

    public function __construct()
    {
        $this->chatUsuarios = new ArrayCollection();
        $this->bloqueo = new ArrayCollection();
        $this->bloqueos = new ArrayCollection();
        $this->bloqueosRealizados = new ArrayCollection();
        $this->bloqueosRecibidos = new ArrayCollection();
        $this->mensajes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getLatitud(): ?float
    {
        return $this->latitud;
    }

    public function setLatitud(float $latitud): static
    {
        $this->latitud = $latitud;

        return $this;
    }

    public function getLongitud(): ?float
    {
        return $this->longitud;
    }

    public function setLongitud(float $longitud): static
    {
        $this->longitud = $longitud;

        return $this;
    }

    public function getUltimaConexion(): ?\DateTime
    {
        return $this->ultimaConexion;
    }

    public function setUltimaConexion(\DateTime $ultimaConexion): static
    {
        $this->ultimaConexion = $ultimaConexion;

        return $this;
    }

    public function isEnLinea(): ?bool
    {
        return $this->enLinea;
    }

    public function setEnLinea(bool $enLinea): static
    {
        $this->enLinea = $enLinea;

        return $this;
    }

   





  

    /**
     * @return Collection<int, Bloqueos>
     */
    public function getBloqueo(): Collection
    {
        return $this->bloqueo;
    }

    public function addBloqueo(Bloqueos $bloqueo): static
    {
        if (!$this->bloqueo->contains($bloqueo)) {
            $this->bloqueo->add($bloqueo);
            $bloqueo->setUser($this);
        }

        return $this;
    }

    public function removeBloqueo(Bloqueos $bloqueo): static
    {
        if ($this->bloqueo->removeElement($bloqueo)) {
            // set the owning side to null (unless already changed)
            if ($bloqueo->getUser() === $this) {
                $bloqueo->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Bloqueos>
     */
    public function getBloqueos(): Collection
    {
        return $this->bloqueos;
    }

    public function getUserToken(): ?string
    {
        return $this->userToken;
    }

    public function setUserToken(string $userToken): static
    {
        $this->userToken = $userToken;

        return $this;
    }

    /**
     * @return Collection<int, Bloqueos>
     */
    public function getBloqueosRealizados(): Collection
    {
        return $this->bloqueosRealizados;
    }

    public function addBloqueosRealizado(Bloqueos $bloqueosRealizado): static
    {
        if (!$this->bloqueosRealizados->contains($bloqueosRealizado)) {
            $this->bloqueosRealizados->add($bloqueosRealizado);
            $bloqueosRealizado->setUsuarioBloqueador($this);
        }

        return $this;
    }

    public function removeBloqueosRealizado(Bloqueos $bloqueosRealizado): static
    {
        if ($this->bloqueosRealizados->removeElement($bloqueosRealizado)) {
            // set the owning side to null (unless already changed)
            if ($bloqueosRealizado->getUsuarioBloqueador() === $this) {
                $bloqueosRealizado->setUsuarioBloqueador(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Bloqueos>
     */
    public function getBloqueosRecibidos(): Collection
    {
        return $this->bloqueosRecibidos;
    }

    public function addBloqueosRecibido(Bloqueos $bloqueosRecibido): static
    {
        if (!$this->bloqueosRecibidos->contains($bloqueosRecibido)) {
            $this->bloqueosRecibidos->add($bloqueosRecibido);
            $bloqueosRecibido->setUsuarioBloqueado($this);
        }

        return $this;
    }

    public function removeBloqueosRecibido(Bloqueos $bloqueosRecibido): static
    {
        if ($this->bloqueosRecibidos->removeElement($bloqueosRecibido)) {
            // set the owning side to null (unless already changed)
            if ($bloqueosRecibido->getUsuarioBloqueado() === $this) {
                $bloqueosRecibido->setUsuarioBloqueado(null);
            }
        }

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
            $chatUsuario->setUsuario($this);
        }

        return $this;
    }

    public function removeChatUsuario(ChatUsuarios $chatUsuario): static
    {
        if ($this->chatUsuarios->removeElement($chatUsuario)) {
            // set the owning side to null (unless already changed)
            if ($chatUsuario->getUsuario() === $this) {
                $chatUsuario->setUsuario(null);
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
            $mensaje->setUsuario($this);
        }

        return $this;
    }

    public function removeMensaje(Mensajes $mensaje): static
    {
        if ($this->mensajes->removeElement($mensaje)) {
            // set the owning side to null (unless already changed)
            if ($mensaje->getUsuario() === $this) {
                $mensaje->setUsuario(null);
            }
        }

        return $this;
    }
}
