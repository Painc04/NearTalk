<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

// Cargar variables de entorno
if (file_exists(__DIR__ . '/../.env')) {
    (new Dotenv())->bootEnv(__DIR__ . '/../.env');
}

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();
$container = $kernel->getContainer();

$doctrine = $container->get('doctrine');
$em = $doctrine->getManager();

$email = $argv[1] ?? 'Admin@add.com';

$user = $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => $email]);
if (!$user) {
    echo "Usuario not found: $email\n";
    exit(1);
}

$storedPassword = $user->getPassword();
$token = hash('sha256', (string) $storedPassword);

echo $token . "\n";

$kernel->shutdown();
