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

#[Route('/api/bloqueo', name: 'api_bloqueo_')]
class BloqueoController extends AbstractController
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    // API/BLOQUEO/BLOQUEAR  [POST] - Bloquear un usuario
    #[Route('/bloquear', name: 'bloquear', methods: ['POST'])]
    public function blockUser(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];

        // Validar que se reciban api_key y token_user
        if (!isset($data['api_key']) || !isset($data['token_user'])) {
            return ApiResponseService::error('api_key y token_user requeridos', 'AUTH_001', null, 400);
        }

        // Validar que se reciba el token del usuario a bloquear
        if (!isset($data['usuario_bloquear_token'])) {
            return ApiResponseService::error('usuario_bloquear_token requerido', 'BLOCK_001', null, 400);
        }

        // Validar api_key
        $expectedApiKey = $_ENV['API_KEY'] ?? getenv('API_KEY') ?? null;
        if (!$expectedApiKey) {
            return ApiResponseService::error('Configuración de servidor inválida (API key no configurada)', 'AUTH_007', null, 500);
        }
        if (!is_string($data['api_key']) || !hash_equals((string) $expectedApiKey, (string) $data['api_key'])) {
            return ApiResponseService::error('API key inválida', 'AUTH_006', null, 401);
        }

        // Validar token del usuario que realiza el bloqueo
        $bloqueadorId = $this->getUserIdByToken((string) $data['token_user']);
        if (!$bloqueadorId) {
            return ApiResponseService::error('Token inválido o expirado', 'AUTH_009', null, 401);
        }

        $usuarioBloqueador = $this->entityManager->getRepository(User::class)->find($bloqueadorId);
        if (!$usuarioBloqueador) {
            return ApiResponseService::error('Usuario bloqueador no encontrado', 'AUTH_010', null, 404);
        }

        // Validar token del usuario a bloquear
        $bloqueadoId = $this->getUserIdByToken((string) $data['usuario_bloquear_token']);
        if (!$bloqueadoId) {
            return ApiResponseService::error('Token del usuario a bloquear inválido', 'BLOCK_002', null, 404);
        }

        // Verificar que no se intente bloquear a sí mismo
        if ($bloqueadorId === $bloqueadoId) {
            return ApiResponseService::error('No puedes bloquearte a ti mismo', 'BLOCK_003', null, 400);
        }

        $usuarioBloqueado = $this->entityManager->getRepository(User::class)->find($bloqueadoId);
        if (!$usuarioBloqueado) {
            return ApiResponseService::error('Usuario a bloquear no encontrado', 'BLOCK_004', null, 404);
        }

        // Verificar si ya existe el bloqueo
        $bloqueoExistente = $this->entityManager->getRepository(Bloqueos::class)->findOneBy([
            'usuarioBloqueador' => $usuarioBloqueador,
            'usuarioBloqueado' => $usuarioBloqueado,
        ]);

        if ($bloqueoExistente) {
            return ApiResponseService::error('Este usuario ya está bloqueado', 'BLOCK_005', null, 400);
        }

        // Crear el bloqueo
        $bloqueo = new Bloqueos();
        $bloqueo->setUsuarioBloqueador($usuarioBloqueador);
        $bloqueo->setUsuarioBloqueado($usuarioBloqueado);
        $bloqueo->setFechaBloqueo(new \DateTime());

        $this->entityManager->persist($bloqueo);
        $this->entityManager->flush();

        return ApiResponseService::success([
            'bloqueo_id' => $bloqueo->getId(),
            'usuario_bloqueador_id' => $usuarioBloqueador->getId(),
            'usuario_bloqueado_id' => $usuarioBloqueado->getId(),
            'fecha_bloqueo' => $bloqueo->getFechaBloqueo()->format('Y-m-d H:i:s'),
        ], 'Usuario bloqueado exitosamente', 200);
    }

    // API/BLOQUEO/DESBLOQUEAR  [DELETE] - Desbloquear un usuario
    #[Route('/desbloquear', name: 'desbloquear', methods: ['DELETE'])]
    public function unblockUser(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];

        // Validar que se reciban api_key y token_user
        if (!isset($data['api_key']) || !isset($data['token_user'])) {
            return ApiResponseService::error('api_key y token_user requeridos', 'AUTH_001', null, 400);
        }

        // Validar que se reciba el token del usuario bloqueado
        if (!isset($data['user_token'])) {
            return ApiResponseService::error('user_token del usuario bloqueado requerido', 'UNBLOCK_001', null, 400);
        }

        // Validar api_key
        $expectedApiKey = $_ENV['API_KEY'] ?? getenv('API_KEY') ?? null;
        if (!$expectedApiKey) {
            return ApiResponseService::error('Configuración de servidor inválida (API key no configurada)', 'AUTH_007', null, 500);
        }
        if (!is_string($data['api_key']) || !hash_equals((string) $expectedApiKey, (string) $data['api_key'])) {
            return ApiResponseService::error('API key inválida', 'AUTH_006', null, 401);
        }

        // Validar token del usuario que realiza el desbloqueo
        $desbloqueadorId = $this->getUserIdByToken((string) $data['token_user']);
        if (!$desbloqueadorId) {
            return ApiResponseService::error('Token inválido o expirado', 'AUTH_009', null, 401);
        }

        $usuarioDesbloqueador = $this->entityManager->getRepository(User::class)->find($desbloqueadorId);
        if (!$usuarioDesbloqueador) {
            return ApiResponseService::error('Usuario no encontrado', 'AUTH_010', null, 404);
        }

        // Validar token del usuario bloqueado
        $bloqueadoId = $this->getUserIdByToken((string) $data['user_token']);
        if (!$bloqueadoId) {
            return ApiResponseService::error('Token del usuario bloqueado inválido', 'UNBLOCK_002', null, 404);
        }

        $usuarioBloqueado = $this->entityManager->getRepository(User::class)->find($bloqueadoId);
        if (!$usuarioBloqueado) {
            return ApiResponseService::error('Usuario bloqueado no encontrado', 'UNBLOCK_003', null, 404);
        }

        // Buscar el bloqueo existente
        $bloqueo = $this->entityManager->getRepository(Bloqueos::class)->findOneBy([
            'usuarioBloqueador' => $usuarioDesbloqueador,
            'usuarioBloqueado' => $usuarioBloqueado,
        ]);

        if (!$bloqueo) {
            return ApiResponseService::error('No existe un bloqueo activo con este usuario', 'UNBLOCK_004', null, 404);
        }

        // Eliminar el bloqueo
        $bloqueoId = $bloqueo->getId();
        $this->entityManager->remove($bloqueo);
        $this->entityManager->flush();

        return ApiResponseService::success([
            'bloqueo_id' => $bloqueoId,
            'usuario_desbloqueador_id' => $usuarioDesbloqueador->getId(),
            'usuario_desbloqueado_id' => $usuarioBloqueado->getId(),
            'desbloqueado' => true,
        ], 'Usuario desbloqueado exitosamente', 200);
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
