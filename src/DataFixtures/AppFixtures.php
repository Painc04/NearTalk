<?php

namespace App\DataFixtures;

use App\Entity\Bloqueos;
use App\Entity\Chats;
use App\Entity\ChatUsuarios;
use App\Entity\Mensajes;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // =============== USUARIOS ===============
        $usuarios = [];

        // Usuario Admin
        $admin = new User();
        $admin->setEmail('admin@redsocial.com');
        $admin->setUsername('admin');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, '12345678'));
        $admin->setLatitud(40.4168);
        $admin->setLongitud(-3.7038);
        $admin->setEnLinea(true);
        $admin->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        $admin->setUserToken(bin2hex(random_bytes(32)));
        $admin->setUltimaConexion(new \DateTime('now'));
        $manager->persist($admin);
        $usuarios[] = $admin;

        // Usuarios normales
        $nombresUsuarios = [
            ['Juan Pérez', 'juan@example.com', 'juan', 40.4200, -3.7000],
            ['María García', 'maria@example.com', 'maria', 40.4150, -3.7100],
            ['Carlos López', 'carlos@example.com', 'carlos', 40.4180, -3.6950],
            ['Ana Martínez', 'ana@example.com', 'ana', 40.4220, -3.7080],
            ['Pedro Sánchez', 'pedro@example.com', 'pedro', 40.4130, -3.7020],
            ['Laura Fernández', 'laura@example.com', 'laura', 40.4190, -3.7060],
            ['David Rodríguez', 'david@example.com', 'david', 40.4160, -3.7090],
            ['Sara González', 'sara@example.com', 'sara', 40.4210, -3.7010],
        ];

        foreach ($nombresUsuarios as $index => $userData) {
            $usuario = new User();
            $usuario->setEmail($userData[1]);
            $usuario->setUsername($userData[2]);
            $usuario->setPassword($this->passwordHasher->hashPassword($usuario, 'password123'));
            $usuario->setLatitud($userData[3]);
            $usuario->setLongitud($userData[4]);
            $usuario->setEnLinea($index % 2 == 0); // Alternamos online/offline
            $usuario->setRoles(['ROLE_USER']);
            $usuario->setUserToken(bin2hex(random_bytes(32)));
            $usuario->setUltimaConexion(new \DateTime('-' . rand(1, 60) . ' minutes'));
            $manager->persist($usuario);
            $usuarios[] = $usuario;
        }

        // =============== CHATS ===============
        $chats = [];

        // Chat privado 1: Juan y María (siempre temporal)
        $chat1 = new Chats();
        $chat1->setChatToken(bin2hex(random_bytes(32)));
        $chat1->setTipo('privado');
        $chat1->setTemporal(true); // Los privados son siempre temporales
        $chat1->setFechaCreacion(new \DateTime('-5 days'));
        $manager->persist($chat1);
        $chats[] = $chat1;

        // Chat privado 2: Carlos y Ana (siempre temporal)
        $chat2 = new Chats();
        $chat2->setChatToken(bin2hex(random_bytes(32)));
        $chat2->setTipo('privado');
        $chat2->setTemporal(true); // Los privados son siempre temporales
        $chat2->setFechaCreacion(new \DateTime('-3 days'));
        $manager->persist($chat2);
        $chats[] = $chat2;

        // Chat privado 3: Pedro y Laura (siempre temporal)
        $chat3 = new Chats();
        $chat3->setChatToken(bin2hex(random_bytes(32)));
        $chat3->setTipo('privado');
        $chat3->setTemporal(true); // Los privados son siempre temporales
        $chat3->setFechaCreacion(new \DateTime('-1 hour'));
        $manager->persist($chat3);
        $chats[] = $chat3;

        // Chat general (fijo, token fijo)
        $chat4 = new Chats();
        $chat4->setChatToken('CHAT_PUBLICO_GENERAL_TOKEN_FIJO_12345'); // Mismo token que en GeneralController
        $chat4->setTipo('general');
        $chat4->setTemporal(false); // El general NO es temporal
        $chat4->setFechaCreacion(new \DateTime('-7 days'));
        $manager->persist($chat4);
        $chats[] = $chat4;

        // =============== CHAT USUARIOS ===============
        // Chat 1: Juan y María
        $chatUsuario1 = new ChatUsuarios();
        $chatUsuario1->setChat($chat1);
        $chatUsuario1->setUsuario($usuarios[1]); // Juan
        $chatUsuario1->setFechaUnion(new \DateTime('-5 days'));
        $manager->persist($chatUsuario1);

        $chatUsuario2 = new ChatUsuarios();
        $chatUsuario2->setChat($chat1);
        $chatUsuario2->setUsuario($usuarios[2]); // María
        $chatUsuario2->setFechaUnion(new \DateTime('-5 days'));
        $manager->persist($chatUsuario2);

        // Chat 2: Carlos y Ana
        $chatUsuario3 = new ChatUsuarios();
        $chatUsuario3->setChat($chat2);
        $chatUsuario3->setUsuario($usuarios[3]); // Carlos
        $chatUsuario3->setFechaUnion(new \DateTime('-3 days'));
        $manager->persist($chatUsuario3);

        $chatUsuario4 = new ChatUsuarios();
        $chatUsuario4->setChat($chat2);
        $chatUsuario4->setUsuario($usuarios[4]); // Ana
        $chatUsuario4->setFechaUnion(new \DateTime('-3 days'));
        $manager->persist($chatUsuario4);

        // Chat 3: Pedro y Laura
        $chatUsuario5 = new ChatUsuarios();
        $chatUsuario5->setChat($chat3);
        $chatUsuario5->setUsuario($usuarios[5]); // Pedro
        $chatUsuario5->setFechaUnion(new \DateTime('-1 hour'));
        $manager->persist($chatUsuario5);

        $chatUsuario6 = new ChatUsuarios();
        $chatUsuario6->setChat($chat3);
        $chatUsuario6->setUsuario($usuarios[6]); // Laura
        $chatUsuario6->setFechaUnion(new \DateTime('-1 hour'));
        $manager->persist($chatUsuario6);

        // Chat 4 (General): David, Sara y Admin
        $chatUsuario7 = new ChatUsuarios();
        $chatUsuario7->setChat($chat4);
        $chatUsuario7->setUsuario($usuarios[7]); // David
        $chatUsuario7->setFechaUnion(new \DateTime('-7 days'));
        $manager->persist($chatUsuario7);

        $chatUsuario8 = new ChatUsuarios();
        $chatUsuario8->setChat($chat4);
        $chatUsuario8->setUsuario($usuarios[8]); // Sara
        $chatUsuario8->setFechaUnion(new \DateTime('-7 days'));
        $manager->persist($chatUsuario8);

        $chatUsuario9 = new ChatUsuarios();
        $chatUsuario9->setChat($chat4);
        $chatUsuario9->setUsuario($admin);
        $chatUsuario9->setFechaUnion(new \DateTime('-7 days'));
        $manager->persist($chatUsuario9);

        // =============== MENSAJES ===============
        // Mensajes del Chat 1 (Juan y María)
        $mensaje1 = new Mensajes();
        $mensaje1->setChat($chat1);
        $mensaje1->setUsuario($usuarios[1]); // Juan
        $mensaje1->setMensaje('Hola María, ¿cómo estás?');
        $mensaje1->setFechaEnvio(new \DateTime('-5 days +10 minutes'));
        $mensaje1->setEsSistema(false);
        $manager->persist($mensaje1);

        $mensaje2 = new Mensajes();
        $mensaje2->setChat($chat1);
        $mensaje2->setUsuario($usuarios[2]); // María
        $mensaje2->setMensaje('¡Hola Juan! Muy bien, ¿y tú?');
        $mensaje2->setFechaEnvio(new \DateTime('-5 days +15 minutes'));
        $mensaje2->setEsSistema(false);
        $manager->persist($mensaje2);

        $mensaje3 = new Mensajes();
        $mensaje3->setChat($chat1);
        $mensaje3->setUsuario($usuarios[1]); // Juan
        $mensaje3->setMensaje('Genial, ¿quedamos para tomar algo?');
        $mensaje3->setFechaEnvio(new \DateTime('-5 days +20 minutes'));
        $mensaje3->setEsSistema(false);
        $manager->persist($mensaje3);

        // Mensajes del Chat 2 (Carlos y Ana)
        $mensaje4 = new Mensajes();
        $mensaje4->setChat($chat2);
        $mensaje4->setUsuario($usuarios[3]); // Carlos
        $mensaje4->setMensaje('Ana, ¿has visto el proyecto nuevo?');
        $mensaje4->setFechaEnvio(new \DateTime('-3 days'));
        $mensaje4->setEsSistema(false);
        $manager->persist($mensaje4);

        $mensaje5 = new Mensajes();
        $mensaje5->setChat($chat2);
        $mensaje5->setUsuario($usuarios[4]); // Ana
        $mensaje5->setMensaje('Sí, parece interesante. ¿Hablamos mañana?');
        $mensaje5->setFechaEnvio(new \DateTime('-3 days +30 minutes'));
        $mensaje5->setEsSistema(false);
        $manager->persist($mensaje5);

        // Mensajes del Chat 3 Temporal (Pedro y Laura) - Chat privado
        $mensaje6 = new Mensajes();
        $mensaje6->setChat($chat3);
        $mensaje6->setUsuario($usuarios[5]); // Pedro
        $mensaje6->setMensaje('Hola Laura');
        $mensaje6->setFechaEnvio(new \DateTime('-1 hour'));
        $mensaje6->setEsSistema(false);
        $manager->persist($mensaje6);

        // Mensajes del Chat 4 General
        $mensaje7 = new Mensajes();
        $mensaje7->setChat($chat4);
        $mensaje7->setUsuario(null); // Mensaje del sistema
        $mensaje7->setMensaje('Chat general creado');
        $mensaje7->setFechaEnvio(new \DateTime('-7 days'));
        $mensaje7->setEsSistema(true);
        $manager->persist($mensaje7);

        $mensaje8 = new Mensajes();
        $mensaje8->setChat($chat4);
        $mensaje8->setUsuario($admin);
        $mensaje8->setMensaje('¡Bienvenidos al chat general!');
        $mensaje8->setFechaEnvio(new \DateTime('-7 days +5 minutes'));
        $mensaje8->setEsSistema(false);
        $manager->persist($mensaje8);

        $mensaje9 = new Mensajes();
        $mensaje9->setChat($chat4);
        $mensaje9->setUsuario($usuarios[7]); // David
        $mensaje9->setMensaje('Gracias por la invitación');
        $mensaje9->setFechaEnvio(new \DateTime('-7 days +10 minutes'));
        $mensaje9->setEsSistema(false);
        $manager->persist($mensaje9);

        $mensaje10 = new Mensajes();
        $mensaje10->setChat($chat4);
        $mensaje10->setUsuario($usuarios[8]); // Sara
        $mensaje10->setMensaje('¡Hola a todos!');
        $mensaje10->setFechaEnvio(new \DateTime('-7 days +15 minutes'));
        $mensaje10->setEsSistema(false);
        $manager->persist($mensaje10);

        // =============== BLOQUEOS ===============
        // María bloquea a Carlos
        $bloqueo1 = new Bloqueos();
        $bloqueo1->setUsuarioBloqueador($usuarios[2]); // María
        $bloqueo1->setUsuarioBloqueado($usuarios[3]); // Carlos
        $bloqueo1->setFechaBloqueo(new \DateTime('-2 days'));
        $manager->persist($bloqueo1);

        // Juan bloquea a Pedro
        $bloqueo2 = new Bloqueos();
        $bloqueo2->setUsuarioBloqueador($usuarios[1]); // Juan
        $bloqueo2->setUsuarioBloqueado($usuarios[5]); // Pedro
        $bloqueo2->setFechaBloqueo(new \DateTime('-1 day'));
        $manager->persist($bloqueo2);

        // Laura bloquea a David
        $bloqueo3 = new Bloqueos();
        $bloqueo3->setUsuarioBloqueador($usuarios[6]); // Laura
        $bloqueo3->setUsuarioBloqueado($usuarios[7]); // David
        $bloqueo3->setFechaBloqueo(new \DateTime('-3 hours'));
        $manager->persist($bloqueo3);

        $manager->flush();
    }
}
