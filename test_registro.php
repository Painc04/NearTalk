<?php
/**
 * Script simple de prueba para registro
 */

$url = 'http://localhost/RedSocial/public/api/registro';

$data = [
    'email' => 'test' . time() . '@example.com',
    'username' => 'testuser' . time(),
    'password' => 'password123'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response:\n";
echo $response . "\n";

// Si fue exitoso, decodificar JSON
if (strpos($response, '{') === 0) {
    $json = json_decode($response, true);
    echo "\nJSON Decodificado:\n";
    print_r($json);
}
