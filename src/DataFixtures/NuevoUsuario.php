<?php

namespace App\DataFixtures;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class NuevoUsuario extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }
    public function load(ObjectManager $manager): void
    {
        $nombre = 'Admin@add.com';
        $plainPassword = '12345678';
        $user = new User();
        $user->setEmail($nombre);
        $user->setUsername('admin');
        $user->setLatitud(0.0);
        $user->setLongitud(0.0);
        $user->setEnLinea(false);
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN' ]);
        $user->setUserToken(bin2hex(random_bytes(32)));
        // Encriptación de la contraseña
        $hashed = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashed);
        // Guarda el registro en la base de datos.
        $manager->persist($user);
        $manager->flush();  

    }
}
