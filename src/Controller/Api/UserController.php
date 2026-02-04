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

#[Route('/api/usuarios', name: 'api_usuarios_')]
class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    // API/USUARIOS/PERFIL  [POST] - Obtener mi perfil
    #[Route('/perfil', name: 'get_my_profile', methods: ['POST'])]
    public function getMyProfile(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];

        if (!isset($data['api_key']) || !isset($data['token_user'])) {
            return ApiResponseService::error('api_key y token_user requeridos', 'AUTH_001', null, 400);
        }

        // Validar api_key
        $expectedApiKey = $_ENV['API_KEY'] ?? getenv('API_KEY') ?? null;
        if (!$expectedApiKey) {
            return ApiResponseService::error('Configuración de servidor inválida (API key no configurada)', 'AUTH_007', null, 500);
        }
        if (!is_string($data['api_key']) || !hash_equals((string) $expectedApiKey, (string) $data['api_key'])) {
            return ApiResponseService::error('API key inválida', 'AUTH_006', null, 401);
        }

        // Validar token del usuario
        $userId = $this->getUserIdByToken((string) $data['token_user']);
        if (!$userId) {
            return ApiResponseService::error('Token inválido o expirado', 'AUTH_009', null, 401);
        }

        $usuario = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$usuario) {
            return ApiResponseService::error('Usuario no encontrado', 'AUTH_010', null, 404);
        }

        return ApiResponseService::success([
            'usuario_id' => $usuario->getId(),
            'username' => $usuario->getUsername(),
            'email' => $usuario->getEmail(),
            'user_token' => $usuario->getUserToken(),
            'roles' => $usuario->getRoles(),
            'en_linea' => $usuario->isEnLinea(),
            'latitud' => $usuario->getLatitud(),
            'longitud' => $usuario->getLongitud(),
            'ultima_conexion' => $usuario->getUltimaConexion()?->format('Y-m-d H:i:s'),
        ], 'Perfil obtenido', 200);
    }


    // API/USUARIOS/PERFIL  [PUT] - Actualizar mi perfil
    #[Route('/perfil', name: 'update_my_profile', methods: ['PUT'])]
    public function updateMyProfile(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];

        if (!isset($data['api_key']) || !isset($data['token_user'])) {
            return ApiResponseService::error('api_key y token_user requeridos', 'AUTH_001', null, 400);
        }

        // Validar api_key
        $expectedApiKey = $_ENV['API_KEY'] ?? getenv('API_KEY') ?? null;
        if (!$expectedApiKey) {
            return ApiResponseService::error('Configuración de servidor inválida (API key no configurada)', 'AUTH_007', null, 500);
        }
        if (!is_string($data['api_key']) || !hash_equals((string) $expectedApiKey, (string) $data['api_key'])) {
            return ApiResponseService::error('API key inválida', 'AUTH_006', null, 401);
        }

        // Validar token del usuario
        $userId = $this->getUserIdByToken((string) $data['token_user']);
        if (!$userId) {
            return ApiResponseService::error('Token inválido o expirado', 'AUTH_009', null, 401);
        }

        $usuario = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$usuario) {
            return ApiResponseService::error('Usuario no encontrado', 'AUTH_010', null, 404);
        }

        // Actualizar campos permitidos
        if (isset($data['username'])) {
            $usuario->setUsername($data['username']);
        }
        if (isset($data['email'])) {
            $usuario->setEmail($data['email']);
        }
        if (isset($data['latitud'])) {
            $usuario->setLatitud((float) $data['latitud']);
        }
        if (isset($data['longitud'])) {
            $usuario->setLongitud((float) $data['longitud']);
        }
        if (isset($data['password'])) {
            $hashedPassword = $this->passwordHasher->hashPassword($usuario, $data['password']);
            $usuario->setPassword($hashedPassword);
        }
        if (isset($data['en_linea'])) {
            $usuario->setEnLinea((bool) $data['en_linea']);
        }

        // Actualizar última conexión automáticamente
        $usuario->setUltimaConexion(new \DateTime());

        $this->entityManager->flush();

        return ApiResponseService::success([
            'usuario_id' => $usuario->getId(),
            'username' => $usuario->getUsername(),
            'email' => $usuario->getEmail(),
            'latitud' => $usuario->getLatitud(),
            'longitud' => $usuario->getLongitud(),
            'en_linea' => $usuario->isEnLinea(),
            'ultima_conexion' => $usuario->getUltimaConexion() ? $usuario->getUltimaConexion()->format('Y-m-d H:i:s') : null,
        ], 'Perfil actualizado', 200);
    }

    // API/USUARIOS  [GET, POST]  - Lista usuarios cercanos
    #[Route('', name: 'list_users', methods: ['GET','POST'])]
    public function listUsers(Request $request): JsonResponse
    {
        // Requerir siempre en el body: api_key y token_user
        $content = json_decode($request->getContent(), true) ?: [];
        if (!isset($content['api_key']) || !isset($content['token_user'])) {
            return ApiResponseService::error('api_key y token_user requeridos en el body', 'AUTH_001', null, 400);
        }

        // Validar api_key
        $expectedApiKey = $_ENV['API_KEY'] ?? getenv('API_KEY') ?? null;
        if (!$expectedApiKey) {
            return ApiResponseService::error('Configuración de servidor inválida (API key no configurada)', 'AUTH_007', null, 500);
        }
        if (!is_string($content['api_key']) || !hash_equals((string) $expectedApiKey, (string) $content['api_key'])) {
            return ApiResponseService::error('API key inválida', 'AUTH_006', null, 401);
        }

        $userId = $this->getUserIdByToken((string) $content['token_user']);
        if (!$userId) {
            return ApiResponseService::error('Token inválido o expirado', 'AUTH_009', null, 401);
        }

        $requestingUser = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$requestingUser) {
            return ApiResponseService::error('Usuario no encontrado', 'AUTH_010', null, 404);
        }

        // Determine center coordinates: prefer provided lat/lon, else user's stored
        $lat = $request->query->get('lat') ?? ($content['lat'] ?? null);
        $lon = $request->query->get('lon') ?? ($content['lon'] ?? null);
        if ($lat === null || $lon === null) {
            $lat = $requestingUser->getLatitud();
            $lon = $requestingUser->getLongitud();
        }
        if ($lat === null || $lon === null) {
            return ApiResponseService::error('Coordenadas de geolocalización requeridas', 'GEO_001', null, 400);
        }

        // Pagination
        $page = max(1, (int) ($request->query->get('page') ?? ($content['page'] ?? 1)));
        $perPage = max(1, min(100, (int) ($request->query->get('per_page') ?? ($content['per_page'] ?? 20))));

        // Radius in km (default 5)
        $radiusKm = 5.0;

        // Fetch candidates (simple approach: all users with coordinates)
        $usuarios = $this->entityManager->getRepository(User::class)->createQueryBuilder('u')
            ->where('u.latitud IS NOT NULL')
            ->andWhere('u.longitud IS NOT NULL')
            ->getQuery()
            ->getResult();

        $results = [];
        foreach ($usuarios as $u) {
            if (!$u instanceof User) {
                continue;
            }
            // Skip requesting user
            if ($u->getId() === $requestingUser->getId()) {
                continue;
            }
            $d = $this->haversineDistance((float)$lat, (float)$lon, (float)$u->getLatitud(), (float)$u->getLongitud());
            if ($d <= $radiusKm) {
                $results[] = [
                    'usuario_id' => $u->getId(),
                    'username' => $u->getUsername(),
                    'email' => $u->getEmail(),
                    'en_linea' => $u->isEnLinea(),
                    'distancia_km' => round($d, 3),
                ];
            }
        }

        // Order by proximity
        usort($results, function ($a, $b) {
            return ($a['distancia_km'] <=> $b['distancia_km']);
        });

        $total = count($results);
        $offset = ($page - 1) * $perPage;
        $paged = array_slice($results, $offset, $perPage);

        return ApiResponseService::success([
            'usuarios' => $paged,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ], 'Usuarios cercanos', 200);
    }

    // API/USUARIOS/{TOKEN}  [POST] - Obtener usuario por token

    #[Route('/{token}', name: 'get_user_by_token_post', methods: ['POST'])]
    public function getUserByTokenPost(string $token, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];

        if (!isset($data['api_key']) || !isset($data['token_user'])) {
            return ApiResponseService::error('api_key y token_user requeridos', 'AUTH_001', null, 400);
        }

        // Validar api_key contra configuración de servidor
        $expectedApiKey = $_ENV['API_KEY'] ?? getenv('API_KEY') ?? null;
        if (!$expectedApiKey) {
            return ApiResponseService::error('Configuración de servidor inválida (API key no configurada)', 'AUTH_007', null, 500);
        }
        if (!is_string($data['api_key']) || !hash_equals((string) $expectedApiKey, (string) $data['api_key'])) {
            return ApiResponseService::error('API key inválida', 'AUTH_006', null, 401);
        }

        // Validar token del usuario que hace la petición
        $requestingUserId = $this->getUserIdByToken((string) $data['token_user']);
        if (!$requestingUserId) {
            return ApiResponseService::error('Token inválido o expirado (requesting user)', 'AUTH_009', null, 401);
        }

        $requestingUser = $this->entityManager->getRepository(User::class)->find($requestingUserId);
        if (!$requestingUser) {
            return ApiResponseService::error('Usuario solicitante no encontrado', 'AUTH_010', null, 404);
        }

        // Resolver token del usuario objetivo (pasado en la ruta)
        $targetUserId = $this->getUserIdByToken($token);
        if (!$targetUserId) {
            return ApiResponseService::error('Usuario objetivo no encontrado (token inválido)', 'USER_001', null, 404);
        }

        $usuario = $this->entityManager->getRepository(User::class)->find($targetUserId);
        if (!$usuario) {
            return ApiResponseService::error('Usuario no encontrado', 'USER_001', null, 404);
        }

        // Información pública que devolvemos
        $payload = [
            'usuario_id' => $usuario->getId(),
            'username' => $usuario->getUsername(),
            'en_linea' => $usuario->isEnLinea(),
            'latitud' => $usuario->getLatitud(),
            'longitud' => $usuario->getLongitud(),
        ];

        return ApiResponseService::success($payload, 'Usuario obtenido', 200);
    }

    #[Route('/{token}', name: 'delete_user_by_token', methods: ['DELETE'])]
    public function deleteUserByToken(string $token, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];

        if (!isset($data['api_key']) || !isset($data['token_user'])) {
            return ApiResponseService::error('api_key y token_user requeridos', 'AUTH_001', null, 400);
        }

        $expectedApiKey = $_ENV['API_KEY'] ?? getenv('API_KEY') ?? null;
        if (!$expectedApiKey) {
            return ApiResponseService::error('Configuración de servidor inválida (API key no configurada)', 'AUTH_007', null, 500);
        }
        if (!is_string($data['api_key']) || !hash_equals((string) $expectedApiKey, (string) $data['api_key'])) {
            return ApiResponseService::error('API key inválida', 'AUTH_006', null, 401);
        }

        // requester
        $requestingUserId = $this->getUserIdByToken((string) $data['token_user']);
        if (!$requestingUserId) {
            return ApiResponseService::error('Token inválido o expirado (requesting user)', 'AUTH_009', null, 401);
        }

        $requestingUser = $this->entityManager->getRepository(User::class)->find($requestingUserId);
        if (!$requestingUser) {
            return ApiResponseService::error('Usuario solicitante no encontrado', 'AUTH_010', null, 404);
        }

        // target
        $targetUserId = $this->getUserIdByToken($token);
        if (!$targetUserId) {
            return ApiResponseService::error('Usuario objetivo no encontrado (token inválido)', 'USER_001', null, 404);
        }

        $usuario = $this->entityManager->getRepository(User::class)->find($targetUserId);
        if (!$usuario) {
            return ApiResponseService::error('Usuario no encontrado', 'USER_001', null, 404);
        }

        // Only allow deleting own account or admin
        $isAdmin = in_array('ROLE_ADMIN', $requestingUser->getRoles(), true);
        if ($requestingUser->getId() !== $usuario->getId() && !$isAdmin) {
            return ApiResponseService::error('No autorizado para eliminar este usuario', 'AUTH_011', null, 403);
        }

        // Proteger usuarios con rol de administrador
        if (in_array('ROLE_ADMIN', $usuario->getRoles(), true)) {
            return ApiResponseService::error('No se pueden eliminar usuarios administradores', 'USER_004', null, 403);
        }

        // Remove user
        $this->entityManager->remove($usuario);
        $this->entityManager->flush();

        // cleanup tokens file (if file fallback is used)
        $this->fileTokenStoreDelete((int) $targetUserId);

        return ApiResponseService::success([
            'usuario_id' => $targetUserId,
            'deleted' => true,
        ], 'Usuario eliminado', 200);
    }

    // Haversine formula para calcular distancia entre dos puntos geográficos
    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $lat1 = deg2rad($lat1);
        $lat2 = deg2rad($lat2);

        $a = sin($dLat/2) * sin($dLat/2) + sin($dLon/2) * sin($dLon/2) * cos($lat1) * cos($lat2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earthRadiusKm * $c;
    }

    
    // Obtener user ID asociado a un token (APCu o archivo)
    private function getUserIdByToken(string $token): ?int
    {
        if (function_exists('apcu_fetch')) {
            $userId = apcu_fetch('token_user_' . $token);
            if ($userId !== false) {
                return (int) $userId;
            }
        }
        // file fallback
        $path = $this->fileTokenStorePath();
        if (!file_exists($path)) {
            return null;
        }
        $contents = @file_get_contents($path);
        $data = $contents ? json_decode($contents, true) ?? [] : [];
        $key = 'token_' . $token;
        return isset($data[$key]) ? (int) $data[$key] : null;
    }

    // Ruta del archivo para almacenamiento de tokens
    private function fileTokenStorePath(): string
    {
        $dir = dirname(__DIR__, 3) . '/var';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir . '/tokens.json';
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
}
