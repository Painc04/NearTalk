<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\ApiResponseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    // API/LOGIN  [POST] - Iniciar sesión  
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['api_key']) || !isset($data['email']) || !isset($data['password']) || !isset($data['latitud']) || !isset($data['longitud'])) {
            return ApiResponseService::error('api_key, email, password, latitud y longitud requeridos', 'AUTH_001', null, 400);
        }

        // Validar api_key leyendo la clave desde variables de entorno
        $expectedApiKey = $_ENV['API_KEY'] ?? getenv('API_KEY') ?? null;
        if (!$expectedApiKey) {
            return ApiResponseService::error('Configuración de servidor inválida (API key no configurada)', 'AUTH_007', null, 500);
        }
        if (!is_string($data['api_key']) || !hash_equals((string) $expectedApiKey, (string) $data['api_key'])) {
            return ApiResponseService::error('API key inválida', 'AUTH_006', null, 401);
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $data['password'])) {
            return ApiResponseService::error('Credenciales inválidas', 'AUTH_002', null, 401);
        }

        // Actualizar ubicación y estado
        $user->setEnLinea(true);
        $user->setLatitud((float) $data['latitud']);
        $user->setLongitud((float) $data['longitud']);
        $user->setUltimaConexion(new \DateTime());
        $this->entityManager->flush();

        // Generar token aleatorio al iniciar sesión
        try {
            $token = bin2hex(random_bytes(32));
        } catch (\Exception $e) {
            $token = bin2hex(openssl_random_pseudo_bytes(32));
        }

        // Almacenar token en memoria (APCu si está disponible, sino fallback a archivo)
        $this->storeToken($user->getId(), $token);

        return new JsonResponse([
            'success' => true,
            'message' => 'Login exitoso',
            'data' => [
                'usuario_id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'token' => $token,
                'chat_general_token' => 'CHAT_PUBLICO_GENERAL_TOKEN_FIJO_12345'
            ]
        ], 200);
    }

    // API/REGISTRO  [POST] - Registrar nuevo usuario
    #[Route('/api/registro', name: 'api_registro', methods: ['POST'])]
    public function registro(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validar API_KEY
        if (!isset($data['api_key']) || $data['api_key'] !== $_ENV['API_KEY']) {
            return ApiResponseService::error('API key inválida o no proporcionada', 'AUTH_009', null, 401);
        }

        if (!isset($data['email']) || !isset($data['username']) || !isset($data['password']) || !isset($data['latitud']) || !isset($data['longitud'])) {
            return ApiResponseService::error('email, username, password, latitud y longitud requeridos', 'AUTH_003', null, 400);
        }

        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return ApiResponseService::error('Email ya registrado', 'AUTH_004', null, 409);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setUsername($data['username']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        $user->setRoles(['ROLE_USER']);
        $user->setEnLinea(true);
        $user->setLatitud((float) $data['latitud']);
        $user->setLongitud((float) $data['longitud']);
        $user->setUltimaConexion(new \DateTime());
        $user->setUserToken(bin2hex(random_bytes(32))); // Token único del usuario

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Generar access_token aleatorio al registrarse
        try {
            $token = bin2hex(random_bytes(32));
        } catch (\Exception $e) {
            $token = bin2hex(openssl_random_pseudo_bytes(32));
        }

        // Almacenar token en memoria (APCu si está disponible, sino fallback a archivo)
        $this->storeToken($user->getId(), $token);

        return ApiResponseService::success([
            'nombre' => $user->getUsername(),
            'email' => $user->getEmail(),
            'access_token' => $token,
            'chat_general_token' => 'CHAT_PUBLICO_GENERAL_TOKEN_FIJO_12345',
        ], 'Usuario registrado exitosamente', 201);
    }

    // API/LOGOUT  [POST] - Cerrar sesión

    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        try {
            // Accept token from JSON body, form-encoded body, query string, Authorization header or raw body
            $data = json_decode($request->getContent(), true);
            $token = null;

            if (is_array($data) && isset($data['access_token'])) {
                $token = (string) $data['access_token'];
            }

            if (!$token) {
                // form-encoded body (x-www-form-urlencoded)
                $formToken = $request->request->get('access_token');
                if ($formToken) {
                    $token = (string) $formToken;
                }
            }

            if (!$token) {
                // query string
                $queryToken = $request->query->get('access_token');
                if ($queryToken) {
                    $token = (string) $queryToken;
                }
            }

            if (!$token) {
                // Authorization: Bearer <token>
                $authHeader = $request->headers->get('Authorization');
                if ($authHeader && preg_match('/Bearer\s+(\S+)/i', $authHeader, $m)) {
                    $token = (string) $m[1];
                }
            }

            if (!$token) {
                // raw body fallback (text/plain)
                $raw = trim($request->getContent());
                if ($raw !== '') {
                    $token = $raw;
                }
            }

            if (!$token) {
                return new JsonResponse([
                    'success' => false,
                    'error_code' => 'AUTH_008',
                    'message' => 'access_token requerido',
                    'data' => null,
                ], 400);
            }

            $userId = $this->getUserIdByToken($token);

            if (!$userId) {
                return new JsonResponse([
                    'success' => false,
                    'error_code' => 'AUTH_009',
                    'message' => 'Token inválido o expirado',
                    'data' => null,
                ], 401);
            }

            $user = $this->entityManager->getRepository(User::class)->find($userId);
            if (!$user) {
                return new JsonResponse([
                    'success' => false,
                    'error_code' => 'AUTH_010',
                    'message' => 'Usuario no encontrado',
                    'data' => null,
                ], 404);
            }

            $user->setEnLinea(false);
            $this->entityManager->flush();

            // Invalidate token from memory
            $this->deleteToken($token);

            return new JsonResponse([
                'success' => true,
                'message' => 'Sesión cerrada correctamente',
                'data' => null,
            ], 200);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error_code' => 'AUTH_500',
                'message' => 'Error interno al procesar logout',
                'data' => null,
            ], 500);
        }
    }

   

    // --- Token store helpers (APCu preferred, file fallback) ---
    private function storeToken(int $userId, string $token): void
    {
        if (function_exists('apcu_store')) {
            apcu_store('user_token_' . $userId, $token);
            apcu_store('token_user_' . $token, $userId);
            return;
        }

        $this->fileTokenStoreWrite($userId, $token);
    }

    private function deleteTokenForUser(int $userId): void
    {
        if (function_exists('apcu_fetch')) {
            $existing = apcu_fetch('user_token_' . $userId);
            if ($existing !== false) {
                if (function_exists('apcu_delete')) {
                    apcu_delete('user_token_' . $userId);
                    apcu_delete('token_user_' . $existing);
                }
                return;
            }
            // fall through to file fallback if APCu has no entry
        }

        $this->fileTokenStoreDelete($userId);
    }

    private function deleteToken(string $token): void
    {
        if (function_exists('apcu_fetch')) {
            $userId = apcu_fetch('token_user_' . $token);
            if ($userId !== false) {
                if (function_exists('apcu_delete')) {
                    apcu_delete('token_user_' . $token);
                    apcu_delete('user_token_' . $userId);
                }
                return;
            }
            // fall through to file fallback if APCu has no entry
        }

        $this->fileTokenStoreDeleteByToken($token);
    }

    private function getUserIdByToken(string $token): ?int
    {
        if (function_exists('apcu_fetch')) {
            $userId = apcu_fetch('token_user_' . $token);
            if ($userId !== false) {
                return (int) $userId;
            }
            // fall through to file fallback if APCu has no entry
        }

        return $this->fileTokenStoreGetUserId($token);
    }

    private function fileTokenStorePath(): string
    {
        // ensure path points to project-level var folder
        $dir = dirname(__DIR__, 3) . '/var';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir . '/tokens.json';
    }

    private function fileTokenStoreWrite(int $userId, string $token): void
    {
        $path = $this->fileTokenStorePath();
        $data = [];
        if (file_exists($path)) {
            $contents = @file_get_contents($path);
            $data = $contents ? json_decode($contents, true) ?? [] : [];
        }
        // remove previous token for this user if any (keep mapping consistent)
        $userKey = 'user_' . $userId;
        if (isset($data[$userKey])) {
            $oldToken = $data[$userKey];
            unset($data[$userKey]);
            unset($data['token_' . $oldToken]);
        }

        $data[$userKey] = $token;
        $data['token_' . $token] = $userId;
        @file_put_contents($path, json_encode($data));
    }

    private function fileTokenStoreDelete(int $userId): void
    {
        $path = $this->fileTokenStorePath();
        if (!file_exists($path)) {
            return;
        }
        $contents = @file_get_contents($path);
        $data = $contents ? json_decode($contents, true) ?? [] : [];
        $userKey = 'user_' . $userId;
        if (isset($data[$userKey])) {
            $token = $data[$userKey];
            unset($data[$userKey]);
            unset($data['token_' . $token]);
            @file_put_contents($path, json_encode($data));
        }
    }

    private function fileTokenStoreDeleteByToken(string $token): void
    {
        $path = $this->fileTokenStorePath();
        if (!file_exists($path)) {
            return;
        }
        $contents = @file_get_contents($path);
        $data = $contents ? json_decode($contents, true) ?? [] : [];
        $tokenKey = 'token_' . $token;
        if (isset($data[$tokenKey])) {
            $userId = $data[$tokenKey];
            unset($data[$tokenKey]);
            unset($data['user_' . $userId]);
            @file_put_contents($path, json_encode($data));
        }
    }

    private function fileTokenStoreGetUserId(string $token): ?int
    {
        $path = $this->fileTokenStorePath();
        if (!file_exists($path)) {
            return null;
        }
        $contents = @file_get_contents($path);
        $data = $contents ? json_decode($contents, true) ?? [] : [];
        $tokenKey = 'token_' . $token;
        return isset($data[$tokenKey]) ? (int) $data[$tokenKey] : null;
    }
}
