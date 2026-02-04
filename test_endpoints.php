<?php
/**
 * Script de prueba para los endpoints de la API
 */

require_once 'vendor/autoload.php';

// Cargar Symfony
$_SERVER['APP_ENV'] = 'dev';
$_SERVER['APP_DEBUG'] = true;
$_SERVER['REQUEST_METHOD'] = 'POST';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernel;

// Test 1: Registro
echo "\n=== TEST 1: REGISTRO ===\n";
$ch = curl_init('http://localhost/RedSocial/public/api/registro');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => 'test@example.com',
    'username' => 'testuser',
    'password' => 'password123'
]));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

$ch = curl_init('http://localhost/RedSocial/public/api/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => 'test@example.com',
    'password' => 'password123'
]));
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

// Test 3: Obtener perfil del usuario autenticado
echo "\n=== TEST 3: OBTENER PERFIL ===\n";
$ch = curl_init('http://localhost/RedSocial/public/api/usuarios/perfil');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

// Test 4: Obtener perfil por token
echo "\n=== TEST 4: OBTENER PERFIL POR TOKEN ===\n";
$ch = curl_init('http://localhost/RedSocial/public/api/usuarios/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

// Test 5: Listar usuarios
echo "\n=== TEST 5: LISTAR USUARIOS ===\n";
$ch = curl_init('http://localhost/RedSocial/public/api/usuarios');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

// Test 6: Logout
echo "\n=== TEST 6: LOGOUT ===\n";
$ch = curl_init('http://localhost/RedSocial/public/api/auth/logout');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

// Limpiar
@unlink('cookies.txt');
echo "\n=== PRUEBAS COMPLETADAS ===\n";
