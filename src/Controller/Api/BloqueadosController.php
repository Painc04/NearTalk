<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Entity\Bloqueos;
use App\Service\ApiResponseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/bloqueados', name: 'api_bloqueados_')]
class BloqueadosController extends AbstractController
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    // API/BLOQUEADOS  [POST] - Listar usuarios bloqueados
    #[Route('', name: 'listar', methods: ['POST'])]
    public function listBlockedUsers(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];

        // Validar que se reciban api_key y token_user
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

        // Obtener todos los bloqueos realizados por este usuario
        $bloqueos = $this->entityManager->getRepository(Bloqueos::class)->findBy([
            'usuarioBloqueador' => $usuario,
        ], ['fechaBloqueo' => 'DESC']);

        $usuariosBloqueados = [];
        foreach ($bloqueos as $bloqueo) {
            $usuarioBloqueado = $bloqueo->getUsuarioBloqueado();
            $usuariosBloqueados[] = [
                'bloqueo_id' => $bloqueo->getId(),
                'usuario_id' => $usuarioBloqueado->getId(),
                'username' => $usuarioBloqueado->getUsername(),
                'email' => $usuarioBloqueado->getEmail(),
                'en_linea' => $usuarioBloqueado->isEnLinea(),
                'fecha_bloqueo' => $bloqueo->getFechaBloqueo()->format('Y-m-d H:i:s'),
            ];
        }

        return ApiResponseService::success([
            'total' => count($usuariosBloqueados),
            'bloqueados' => $usuariosBloqueados,
        ], 'Lista de usuarios bloqueados', 200);
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
}
