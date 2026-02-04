<?php

namespace App\Controller\Api;

use App\Entity\ChatUsuarios;
use App\Entity\Mensajes;
use App\Entity\User;
use App\Service\ApiResponseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class ActualizarController extends AbstractController
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[Route('/actualizar', name: 'actualizar', methods: ['POST'])]
    public function actualizar(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Validar api_key
            if (!isset($data['api_key'])) {
                return ApiResponseService::error('api_key requerido', 'AUTH_001', null, 400);
            }

            $expectedApiKey = $_ENV['API_KEY'] ?? getenv('API_KEY') ?? null;
            if (!$expectedApiKey) {
                return ApiResponseService::error('Configuración de servidor inválida (API key no configurada)', 'AUTH_007', null, 500);
            }
            if (!is_string($data['api_key']) || !hash_equals((string) $expectedApiKey, (string) $data['api_key'])) {
                return ApiResponseService::error('API key inválida', 'AUTH_006', null, 401);
            }

            // Validar user_token
            if (!isset($data['user_token'])) {
                return ApiResponseService::error('user_token requerido', 'AUTH_003', null, 400);
            }

            // Validar token_sala y ultimo_mensaje_id
            if (!isset($data['token_sala'])) {
                return ApiResponseService::error('token_sala requerido', 'CHAT_001', null, 400);
            }

            if (!isset($data['ultimo_mensaje_id'])) {
                return ApiResponseService::error('ultimo_mensaje_id requerido', 'MSG_001', null, 400);
            }

            $userToken = $data['user_token'];
            $tokenSala = $data['token_sala'];
            $ultimoMensajeId = (int) $data['ultimo_mensaje_id'];

            $userId = $this->getUserIdByToken($userToken);

            if (!$userId) {
                return ApiResponseService::error('Token inválido o expirado', 'AUTH_002', null, 401);
            }

            $user = $this->entityManager->getRepository(User::class)->find($userId);

            if (!$user) {
                return ApiResponseService::error('Usuario no encontrado', 'USER_001', null, 404);
            }

            // Actualizar ubicación si se proporciona
            if (isset($data['latitud']) && isset($data['longitud'])) {
                $latitud = (float) $data['latitud'];
                $longitud = (float) $data['longitud'];
                
                $user->setLatitud($latitud);
                $user->setLongitud($longitud);
            }

            // Actualizar última conexión y estado en línea
            $user->setUltimaConexion(new \DateTime());
            $user->setEnLinea(true);
            $this->entityManager->flush();

            // Obtener timestamp de última actualización (si se proporciona)
            $ultimaActualizacion = null;
            if (isset($data['ultima_actualizacion'])) {
                try {
                    $ultimaActualizacion = new \DateTime($data['ultima_actualizacion']);
                } catch (\Exception $e) {
                    // Si el formato es inválido, ignorarlo
                }
            }

            // Si no hay timestamp, obtener datos de los últimos 5 minutos
            if (!$ultimaActualizacion) {
                $ultimaActualizacion = new \DateTime('-5 minutes');
            }

            // Obtener mensajes recientes del chat específico
            $mensajesRecientes = $this->getMensajesRecientes($userId, $tokenSala, $ultimoMensajeId);

            // Obtener usuarios online cercanos (dentro del mismo radio, ejemplo: 10km)
            $usuariosOnline = $this->getUsuariosOnline($userId);

            // Obtener invitaciones pendientes de chats
            $invitacionesPendientes = $this->getInvitacionesPendientes($userId, $ultimaActualizacion);

            return ApiResponseService::success([
                'mensajes_nuevos' => $mensajesRecientes,
                'usuarios_online' => $usuariosOnline,
                'invitaciones' => $invitacionesPendientes,
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            ], 'Actualización exitosa', 200);

        } catch (\Exception $e) {
            return ApiResponseService::error(
                'Error al procesar actualización: ' . $e->getMessage(),
                'UPDATE_001',
                null,
                500
            );
        }
    }

    /**
     * Obtiene mensajes recientes de un chat específico desde un mensaje ID
     */
    private function getMensajesRecientes(int $userId, string $chatToken, int $ultimoMensajeId): array
    {
        // Verificar que el chat exista
        $chat = $this->entityManager->getRepository(\App\Entity\Chats::class)
            ->findOneBy(['chatToken' => $chatToken]);

        if (!$chat) {
            return [];
        }

        // Obtener el usuario
        $usuario = $this->entityManager->getRepository(\App\Entity\User::class)->find($userId);
        if (!$usuario) {
            return [];
        }

        // Verificar que el usuario pertenezca al chat
        $chatUsuario = $this->entityManager->getRepository(ChatUsuarios::class)
            ->createQueryBuilder('cu')
            ->where('cu.usuario = :usuario')
            ->andWhere('cu.chat = :chat')
            ->setParameter('usuario', $usuario)
            ->setParameter('chat', $chat)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$chatUsuario) {
            return [];
        }

        // Obtener mensajes posteriores al último mensaje conocido
        $mensajes = $this->entityManager->getRepository(Mensajes::class)
            ->createQueryBuilder('m')
            ->where('m.chat = :chat')
            ->andWhere('m.id > :ultimoMensajeId')
            ->setParameter('chat', $chat)
            ->setParameter('ultimoMensajeId', $ultimoMensajeId)
            ->orderBy('m.fechaEnvio', 'ASC')
            ->setMaxResults(100) // Limitar a 100 mensajes más recientes
            ->getQuery()
            ->getResult();

        $resultado = [];
        foreach ($mensajes as $mensaje) {
            $usuario = $mensaje->getUsuario();
            $resultado[] = [
                'id' => $mensaje->getId(),
                'chat_token' => $mensaje->getChat() ? $mensaje->getChat()->getChatToken() : null,
                'usuario_id' => $usuario ? $usuario->getId() : null,
                'username' => $usuario ? $usuario->getUsername() : 'Desconocido',
                'contenido' => $mensaje->getMensaje(),
                'fecha_envio' => $mensaje->getFechaEnvio()->format('Y-m-d H:i:s'),
                'es_sistema' => $mensaje->isEsSistema(),
            ];
        }

        return $resultado;
    }

    /**
     * Obtiene usuarios que están online
     */
    private function getUsuariosOnline(int $userId): array
    {
        // Obtener usuarios online (conexión en los últimos 5 minutos)
        $tiempoLimite = new \DateTime('-5 minutes');
        
        $usuarios = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.enLinea = :online')
            ->andWhere('u.ultimaConexion >= :tiempo')
            ->andWhere('u.id != :userId') // Excluir al usuario actual
            ->setParameter('online', true)
            ->setParameter('tiempo', $tiempoLimite)
            ->setParameter('userId', $userId)
            ->orderBy('u.ultimaConexion', 'DESC')
            ->setMaxResults(100) // Limitar a 100 usuarios
            ->getQuery()
            ->getResult();

        $resultado = [];
        foreach ($usuarios as $usuario) {
            $resultado[] = [
                'id' => $usuario->getId(),
                'username' => $usuario->getUsername(),
                'email' => $usuario->getEmail(),
                'latitud' => $usuario->getLatitud(),
                'longitud' => $usuario->getLongitud(),
                'ultima_conexion' => $usuario->getUltimaConexion() 
                    ? $usuario->getUltimaConexion()->format('Y-m-d H:i:s') 
                    : null,
            ];
        }

        return $resultado;
    }

    /**
     * Obtiene invitaciones pendientes de chats (chats nuevos desde última actualización)
     */
    private function getInvitacionesPendientes(int $userId, \DateTime $desde): array
    {
        $usuario = $this->entityManager->getRepository(\App\Entity\User::class)->find($userId);
        if (!$usuario) {
            return [];
        }

        $invitaciones = $this->entityManager->getRepository(ChatUsuarios::class)
            ->createQueryBuilder('cu')
            ->where('cu.usuario = :usuario')
            ->andWhere('cu.fechaUnion >= :desde')
            ->setParameter('usuario', $usuario)
            ->setParameter('desde', $desde)
            ->orderBy('cu.fechaUnion', 'DESC')
            ->getQuery()
            ->getResult();

        $resultado = [];
        foreach ($invitaciones as $invitacion) {
            $chat = $invitacion->getChat();
            $resultado[] = [
                'id' => $invitacion->getId(),
                'chat_token' => $chat ? $chat->getChatToken() : null,
                'fecha_union' => $invitacion->getFechaUnion() 
                    ? $invitacion->getFechaUnion()->format('Y-m-d H:i:s') 
                    : null,
            ];
        }

        return $resultado;
    }

    /**
     * Obtiene el ID de usuario asociado a un token
     */
    private function getUserIdByToken(string $token): ?int
    {
        // Intentar obtener de APCu primero
        if (function_exists('apcu_fetch')) {
            $userId = apcu_fetch('token_user_' . $token);
            if ($userId !== false) {
                return (int) $userId;
            }
        }

        // Fallback a archivo
        return $this->fileTokenStoreGetUserId($token);
    }

    /**
     * Obtiene ruta del archivo de tokens
     */
    private function fileTokenStorePath(): string
    {
        $dir = dirname(__DIR__, 3) . '/var';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir . '/tokens.json';
    }

    /**
     * Obtiene ID de usuario desde archivo de tokens
     */
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
