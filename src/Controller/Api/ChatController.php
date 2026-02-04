<?php

namespace App\Controller\Api;

use App\Entity\Chats;
use App\Entity\ChatUsuarios;
use App\Entity\Mensajes;
use App\Entity\User;
use App\Service\ApiResponseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_chat_')]
class ChatController extends AbstractController
{
    private const CHAT_GENERAL_TOKEN = 'CHAT_PUBLICO_GENERAL_TOKEN_FIJO_12345'; // Token fijo del chat público

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    // CHAT GENERAL - Enviar mensaje al chat público
    #[Route('/general/mensaje', name: 'general_mensaje', methods: ['POST'])]
    public function enviarMensajeGeneral(Request $request): JsonResponse
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

            // Validar chat_token
            if (!isset($data['chat_token'])) {
                return ApiResponseService::error('chat_token requerido', 'CHAT_002', null, 400);
            }

            // Validar contenido del mensaje
            if (!isset($data['contenido']) || trim($data['contenido']) === '') {
                return ApiResponseService::error('Contenido del mensaje requerido', 'MSG_002', null, 400);
            }

            $userToken = $data['user_token'];
            $chatToken = $data['chat_token'];
            $contenido = trim($data['contenido']);

            // Validar que el chat_token sea el del chat público
            if ($chatToken !== self::CHAT_GENERAL_TOKEN) {
                return ApiResponseService::error('Token de chat inválido', 'CHAT_003', null, 400);
            }

            // Obtener usuario por token
            $userId = $this->getUserIdByToken($userToken);

            if (!$userId) {
                return ApiResponseService::error('Token inválido o expirado', 'AUTH_002', null, 401);
            }

            $user = $this->entityManager->getRepository(User::class)->find($userId);

            if (!$user) {
                return ApiResponseService::error('Usuario no encontrado', 'USER_001', null, 404);
            }

            // Verificar si el chat general existe, si no, crearlo
            $chatGeneral = $this->entityManager->getRepository(Chats::class)
                ->findOneBy(['chatToken' => self::CHAT_GENERAL_TOKEN]);
            
            if (!$chatGeneral) {
                $chatGeneral = new Chats();
                $chatGeneral->setTipo('general');
                $chatGeneral->setTemporal(false);
                $chatGeneral->setChatToken(self::CHAT_GENERAL_TOKEN);
                $chatGeneral->setFechaCreacion(new \DateTime());
                $this->entityManager->persist($chatGeneral);
                $this->entityManager->flush();
                
                // Refrescar la entidad para obtener el ID generado
                $this->entityManager->refresh($chatGeneral);
            }

            // Asegurar que tenemos el ID del chat
            $chatId = $chatGeneral->getId();
            if (!$chatId) {
                return ApiResponseService::error('Error al obtener ID del chat general', 'CHAT_004', null, 500);
            }

            // Obtener el usuario
            $usuario = $this->entityManager->getRepository(User::class)->find($userId);
            if (!$usuario) {
                return ApiResponseService::error('Usuario no encontrado', 'USER_001', null, 404);
            }

            // Verificar si el usuario ya está en el chat general, si no, agregarlo
            $chatUsuario = $this->entityManager->getRepository(ChatUsuarios::class)
                ->createQueryBuilder('cu')
                ->where('cu.chat = :chat')
                ->andWhere('cu.usuario = :usuario')
                ->setParameter('chat', $chatGeneral)
                ->setParameter('usuario', $usuario)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$chatUsuario) {
                $chatUsuario = new ChatUsuarios();
                $chatUsuario->setChat($chatGeneral);
                $chatUsuario->setUsuario($usuario);
                $chatUsuario->setFechaUnion(new \DateTime());
                $this->entityManager->persist($chatUsuario);
                $this->entityManager->flush(); // Flush para guardar la relación
            }

            // Crear el mensaje
            $mensaje = new Mensajes();
            $mensaje->setChat($chatGeneral);
            $mensaje->setUsuario($usuario);
            $mensaje->setMensaje($contenido);
            $mensaje->setFechaEnvio(new \DateTime());
            $mensaje->setEsSistema(false);

            $this->entityManager->persist($mensaje);
            $this->entityManager->flush();

            return ApiResponseService::success([
                'mensaje_id' => $mensaje->getId(),
                'chat_token' => $chatToken,
                'usuario_id' => $userId,
                'username' => $user->getUsername(),
                'contenido' => $mensaje->getMensaje(),
                'fecha_envio' => $mensaje->getFechaEnvio()->format('Y-m-d H:i:s'),
            ], 'Mensaje enviado al chat público', 201);

        } catch (\Exception $e) {
            return ApiResponseService::error(
                'Error al enviar mensaje: ' . $e->getMessage(),
                'MSG_003',
                null,
                500
            );
        }
    }

    // CHAT PRIVADO - Crear nuevo chat privado
    #[Route('/privado', name: 'crear_chat_privado', methods: ['POST'])]
    public function crearChatPrivado(Request $request): JsonResponse
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

            // Validar destinatario_token (token del usuario con quien se quiere chatear)
            if (!isset($data['destinatario_token'])) {
                return ApiResponseService::error('destinatario_token requerido', 'USER_005', null, 400);
            }

            $userToken = $data['user_token'];
            $destinatarioToken = $data['destinatario_token'];

            // Obtener usuario por token (creador del chat)
            $userId = $this->getUserIdByToken($userToken);

            if (!$userId) {
                return ApiResponseService::error('Token inválido o expirado', 'AUTH_002', null, 401);
            }

            // Obtener destinatario por token
            $destinatarioId = $this->getUserIdByToken($destinatarioToken);

            if (!$destinatarioId) {
                return ApiResponseService::error('Token de destinatario inválido o expirado', 'AUTH_008', null, 401);
            }

            // Verificar que el usuario no intente crear un chat consigo mismo
            if ($userId == $destinatarioId) {
                return ApiResponseService::error('No puedes crear un chat contigo mismo', 'CHAT_006', null, 400);
            }

            $usuarioCreador = $this->entityManager->getRepository(User::class)->find($userId);
            $usuarioDestinatario = $this->entityManager->getRepository(User::class)->find($destinatarioId);

            if (!$usuarioCreador) {
                return ApiResponseService::error('Usuario creador no encontrado', 'USER_001', null, 404);
            }

            if (!$usuarioDestinatario) {
                return ApiResponseService::error('Usuario destinatario no encontrado', 'USER_006', null, 404);
            }

            // Verificar si ya existe un chat privado entre estos dos usuarios
            $chatExistente = $this->entityManager->getRepository(Chats::class)
                ->createQueryBuilder('c')
                ->innerJoin('c.chatUsuarios', 'cu1')
                ->innerJoin('c.chatUsuarios', 'cu2')
                ->where('c.tipo = :tipo')
                ->andWhere('cu1.usuario = :usuario1')
                ->andWhere('cu2.usuario = :usuario2')
                ->setParameter('tipo', 'privado')
                ->setParameter('usuario1', $usuarioCreador)
                ->setParameter('usuario2', $usuarioDestinatario)
                ->getQuery()
                ->getOneOrNullResult();

            if ($chatExistente) {
                return ApiResponseService::success([
                    'chat_token' => $chatExistente->getChatToken(),
                    'chat_id' => $chatExistente->getId(),
                    'tipo' => $chatExistente->getTipo(),
                    'temporal' => $chatExistente->isTemporal(),
                    'fecha_creacion' => $chatExistente->getFechaCreacion()->format('Y-m-d H:i:s'),
                ], 'Chat privado ya existe', 200);
            }

            // Crear nuevo chat privado
            $nuevoChat = new Chats();
            $nuevoChat->setTipo('privado');
            $nuevoChat->setTemporal(true); // Los chats privados son temporales
            $nuevoChat->setChatToken(bin2hex(random_bytes(32))); // Token aleatorio
            $nuevoChat->setFechaCreacion(new \DateTime());

            $this->entityManager->persist($nuevoChat);
            $this->entityManager->flush();

            // Agregar ambos usuarios al chat
            $chatUsuario1 = new ChatUsuarios();
            $chatUsuario1->setChat($nuevoChat);
            $chatUsuario1->setUsuario($usuarioCreador);
            $chatUsuario1->setFechaUnion(new \DateTime());

            $chatUsuario2 = new ChatUsuarios();
            $chatUsuario2->setChat($nuevoChat);
            $chatUsuario2->setUsuario($usuarioDestinatario);
            $chatUsuario2->setFechaUnion(new \DateTime());

            $this->entityManager->persist($chatUsuario1);
            $this->entityManager->persist($chatUsuario2);
            $this->entityManager->flush();

            return ApiResponseService::success([
                'chat_token' => $nuevoChat->getChatToken(),
                'chat_id' => $nuevoChat->getId(),
                'tipo' => $nuevoChat->getTipo(),
                'temporal' => $nuevoChat->isTemporal(),
                'fecha_creacion' => $nuevoChat->getFechaCreacion()->format('Y-m-d H:i:s'),
                'usuarios' => [
                    [
                        'usuario_id' => $usuarioCreador->getId(),
                        'username' => $usuarioCreador->getUsername(),
                        'email' => $usuarioCreador->getEmail(),
                    ],
                    [
                        'usuario_id' => $usuarioDestinatario->getId(),
                        'username' => $usuarioDestinatario->getUsername(),
                        'email' => $usuarioDestinatario->getEmail(),
                    ],
                ],
            ], 'Chat privado creado exitosamente', 201);

        } catch (\Exception $e) {
            return ApiResponseService::error(
                'Error al crear chat privado: ' . $e->getMessage(),
                'CHAT_007',
                null,
                500
            );
        }
    }

    // CHAT PRIVADO - Obtener mensajes de chat privado
    #[Route('/privado/{token_chat}', name: 'mensajes_chat_privado', methods: ['POST'])]
    public function obtenerMensajesPrivados(Request $request, string $token_chat): JsonResponse
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

            $userToken = $data['user_token'];

            // Obtener usuario por token
            $userId = $this->getUserIdByToken($userToken);

            if (!$userId) {
                return ApiResponseService::error('Token inválido o expirado', 'AUTH_002', null, 401);
            }

            $usuario = $this->entityManager->getRepository(User::class)->find($userId);

            if (!$usuario) {
                return ApiResponseService::error('Usuario no encontrado', 'USER_001', null, 404);
            }

            // Buscar el chat por token
            $chat = $this->entityManager->getRepository(Chats::class)
                ->findOneBy(['chatToken' => $token_chat]);

            if (!$chat) {
                return ApiResponseService::error('Chat no encontrado', 'CHAT_008', null, 404);
            }

            // Verificar que sea un chat privado
            if ($chat->getTipo() !== 'privado') {
                return ApiResponseService::error('Este endpoint es solo para chats privados', 'CHAT_009', null, 400);
            }

            // Verificar que el usuario pertenece al chat
            $chatUsuario = $this->entityManager->getRepository(ChatUsuarios::class)
                ->createQueryBuilder('cu')
                ->where('cu.chat = :chat')
                ->andWhere('cu.usuario = :usuario')
                ->setParameter('chat', $chat)
                ->setParameter('usuario', $usuario)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$chatUsuario) {
                return ApiResponseService::error('No tienes acceso a este chat', 'CHAT_010', null, 403);
            }

            // Obtener parámetros de query
            $desdeId = $request->query->get('desde_id');
            $limite = $request->query->get('limite', 100);

            // Validar límite
            $limite = (int) $limite;
            if ($limite <= 0 || $limite > 500) {
                $limite = 100;
            }

            // Construir query para obtener mensajes
            $qb = $this->entityManager->getRepository(Mensajes::class)
                ->createQueryBuilder('m')
                ->where('m.chat = :chat')
                ->setParameter('chat', $chat)
                ->orderBy('m.fechaEnvio', 'ASC')
                ->setMaxResults($limite);

            // Si se especifica desde_id, obtener mensajes posteriores a ese ID
            if ($desdeId) {
                $qb->andWhere('m.id > :desdeId')
                   ->setParameter('desdeId', (int) $desdeId);
            }

            $mensajes = $qb->getQuery()->getResult();

            // Formatear mensajes
            $mensajesFormateados = [];
            foreach ($mensajes as $mensaje) {
                $usuarioMensaje = $mensaje->getUsuario();
                $mensajesFormateados[] = [
                    'mensaje_id' => $mensaje->getId(),
                    'usuario_id' => $usuarioMensaje ? $usuarioMensaje->getId() : null,
                    'username' => $usuarioMensaje ? $usuarioMensaje->getUsername() : 'Usuario desconocido',
                    'mensaje' => $mensaje->getMensaje(),
                    'fecha_envio' => $mensaje->getFechaEnvio()->format('Y-m-d H:i:s'),
                    'es_sistema' => $mensaje->isEsSistema(),
                ];
            }

            return ApiResponseService::success([
                'mensajes' => $mensajesFormateados,
                'total' => count($mensajesFormateados),
                'chat_token' => $token_chat,
                'limite' => $limite,
                'desde_id' => $desdeId,
            ], 'Mensajes obtenidos exitosamente', 200);

        } catch (\Exception $e) {
            return ApiResponseService::error(
                'Error al obtener mensajes: ' . $e->getMessage(),
                'MSG_004',
                null,
                500
            );
        }
    }

    // CHAT PRIVADO - Enviar mensaje a chat privado
    #[Route('/privado/{chat_token}/mensaje', name: 'enviar_mensaje_privado', methods: ['POST'])]
    public function enviarMensajePrivado(Request $request, string $chat_token): JsonResponse
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

            // Validar mensaje
            if (!isset($data['mensaje']) || trim($data['mensaje']) === '') {
                return ApiResponseService::error('Mensaje requerido', 'MSG_002', null, 400);
            }

            $userToken = $data['user_token'];
            $mensaje = trim($data['mensaje']);

            // Obtener usuario por token
            $userId = $this->getUserIdByToken($userToken);

            if (!$userId) {
                return ApiResponseService::error('Token inválido o expirado', 'AUTH_002', null, 401);
            }

            $usuario = $this->entityManager->getRepository(User::class)->find($userId);

            if (!$usuario) {
                return ApiResponseService::error('Usuario no encontrado', 'USER_001', null, 404);
            }

            // Buscar el chat por token
            $chat = $this->entityManager->getRepository(Chats::class)
                ->findOneBy(['chatToken' => $chat_token]);

            if (!$chat) {
                return ApiResponseService::error('Chat no encontrado', 'CHAT_008', null, 404);
            }

            // Verificar que sea un chat privado
            if ($chat->getTipo() !== 'privado') {
                return ApiResponseService::error('Este endpoint es solo para chats privados', 'CHAT_009', null, 400);
            }

            // Verificar que el usuario pertenece al chat
            $chatUsuario = $this->entityManager->getRepository(ChatUsuarios::class)
                ->createQueryBuilder('cu')
                ->where('cu.chat = :chat')
                ->andWhere('cu.usuario = :usuario')
                ->setParameter('chat', $chat)
                ->setParameter('usuario', $usuario)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$chatUsuario) {
                return ApiResponseService::error('No tienes acceso a este chat', 'CHAT_010', null, 403);
            }

            // Crear el mensaje
            $nuevoMensaje = new Mensajes();
            $nuevoMensaje->setChat($chat);
            $nuevoMensaje->setUsuario($usuario);
            $nuevoMensaje->setMensaje($mensaje);
            $nuevoMensaje->setFechaEnvio(new \DateTime());
            $nuevoMensaje->setEsSistema(false);

            $this->entityManager->persist($nuevoMensaje);
            $this->entityManager->flush();

            return ApiResponseService::success([
                'mensaje_id' => $nuevoMensaje->getId(),
                'chat_id' => $chat->getId(),
                'chat_token' => $chat->getChatToken(),
                'usuario_id' => $usuario->getId(),
                'username' => $usuario->getUsername(),
                'mensaje' => $nuevoMensaje->getMensaje(),
                'fecha_envio' => $nuevoMensaje->getFechaEnvio()->format('Y-m-d H:i:s'),
            ], 'Mensaje enviado exitosamente', 201);

        } catch (\Exception $e) {
            return ApiResponseService::error(
                'Error al enviar mensaje: ' . $e->getMessage(),
                'MSG_003',
                null,
                500
            );
        }
    }

    // CHAT PRIVADO - Salir de un chat
    #[Route('/privado/{chat_token}/salir', name: 'salir_chat_privado', methods: ['POST'])]
    public function salirChatPrivado(Request $request, string $chat_token): JsonResponse
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

            $userToken = $data['user_token'];

            // Obtener usuario por token
            $userId = $this->getUserIdByToken($userToken);

            if (!$userId) {
                return ApiResponseService::error('Token inválido o expirado', 'AUTH_002', null, 401);
            }

            $usuario = $this->entityManager->getRepository(User::class)->find($userId);

            if (!$usuario) {
                return ApiResponseService::error('Usuario no encontrado', 'USER_001', null, 404);
            }

            // Buscar el chat por token
            $chat = $this->entityManager->getRepository(Chats::class)
                ->findOneBy(['chatToken' => $chat_token]);

            if (!$chat) {
                return ApiResponseService::error('Chat no encontrado', 'CHAT_008', null, 404);
            }

            // Verificar que sea un chat privado
            if ($chat->getTipo() !== 'privado') {
                return ApiResponseService::error('Este endpoint es solo para chats privados', 'CHAT_009', null, 400);
            }

            // Verificar que el usuario pertenece al chat
            $chatUsuario = $this->entityManager->getRepository(ChatUsuarios::class)
                ->createQueryBuilder('cu')
                ->where('cu.chat = :chat')
                ->andWhere('cu.usuario = :usuario')
                ->setParameter('chat', $chat)
                ->setParameter('usuario', $usuario)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$chatUsuario) {
                return ApiResponseService::error('No perteneces a este chat', 'CHAT_010', null, 403);
            }

            // Eliminar al usuario del chat
            $this->entityManager->remove($chatUsuario);
            $this->entityManager->flush();

            // Contar cuántos usuarios quedan en el chat
            $usuariosRestantes = $this->entityManager->getRepository(ChatUsuarios::class)
                ->createQueryBuilder('cu')
                ->select('COUNT(cu.id)')
                ->where('cu.chat = :chat')
                ->setParameter('chat', $chat)
                ->getQuery()
                ->getSingleScalarResult();

            $chatEliminado = false;

            // Si no quedan usuarios, eliminar el chat y todos sus mensajes
            if ($usuariosRestantes == 0) {
                // Eliminar todos los mensajes del chat
                $mensajes = $this->entityManager->getRepository(Mensajes::class)
                    ->findBy(['chat' => $chat]);

                foreach ($mensajes as $mensaje) {
                    $this->entityManager->remove($mensaje);
                }

                // Eliminar el chat
                $this->entityManager->remove($chat);
                $this->entityManager->flush();

                $chatEliminado = true;
            }

            return ApiResponseService::success([
                'usuario_id' => $usuario->getId(),
                'username' => $usuario->getUsername(),
                'chat_token' => $chat_token,
                'chat_eliminado' => $chatEliminado,
                'usuarios_restantes' => (int) $usuariosRestantes,
            ], $chatEliminado ? 'Has salido del chat y el chat ha sido eliminado' : 'Has salido del chat exitosamente', 200);

        } catch (\Exception $e) {
            return ApiResponseService::error(
                'Error al salir del chat: ' . $e->getMessage(),
                'CHAT_011',
                null,
                500
            );
        }
    }

    // CHAT PRIVADO - Eliminar chat temporal
    #[Route('/privado/{chat_token}', name: 'eliminar_chat_privado', methods: ['DELETE'])]
    public function eliminarChatPrivado(Request $request, string $chat_token): JsonResponse
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

            $userToken = $data['user_token'];

            // Obtener usuario por token
            $userId = $this->getUserIdByToken($userToken);

            if (!$userId) {
                return ApiResponseService::error('Token inválido o expirado', 'AUTH_002', null, 401);
            }

            $usuario = $this->entityManager->getRepository(User::class)->find($userId);

            if (!$usuario) {
                return ApiResponseService::error('Usuario no encontrado', 'USER_001', null, 404);
            }

            // Buscar el chat por token
            $chat = $this->entityManager->getRepository(Chats::class)
                ->findOneBy(['chatToken' => $chat_token]);

            if (!$chat) {
                return ApiResponseService::error('Chat no encontrado', 'CHAT_008', null, 404);
            }

            // Verificar que sea un chat temporal
            if (!$chat->isTemporal()) {
                return ApiResponseService::error('Solo se pueden eliminar chats temporales', 'CHAT_012', null, 400);
            }

            // Verificar que el usuario sea admin o el creador del chat
            // Obtener el primer usuario que se unió (considerado creador)
            $primerUsuario = $this->entityManager->getRepository(ChatUsuarios::class)
                ->createQueryBuilder('cu')
                ->where('cu.chat = :chat')
                ->setParameter('chat', $chat)
                ->orderBy('cu.fechaUnion', 'ASC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            $esCreador = $primerUsuario && $primerUsuario->getUsuario()->getId() === $usuario->getId();
            $esAdmin = in_array('ROLE_ADMIN', $usuario->getRoles(), true);

            if (!$esCreador && !$esAdmin) {
                return ApiResponseService::error('Solo el creador del chat o un administrador puede eliminarlo', 'CHAT_013', null, 403);
            }

            // Eliminar todos los mensajes del chat
            $mensajes = $this->entityManager->getRepository(Mensajes::class)
                ->findBy(['chat' => $chat]);

            foreach ($mensajes as $mensaje) {
                $this->entityManager->remove($mensaje);
            }

            // Eliminar todas las relaciones ChatUsuarios
            $chatUsuarios = $this->entityManager->getRepository(ChatUsuarios::class)
                ->findBy(['chat' => $chat]);

            foreach ($chatUsuarios as $chatUsuario) {
                $this->entityManager->remove($chatUsuario);
            }

            // Eliminar el chat
            $this->entityManager->remove($chat);
            $this->entityManager->flush();

            return ApiResponseService::success([
                'chat_token' => $chat_token,
                'eliminado_por' => $usuario->getUsername(),
                'es_admin' => $esAdmin,
            ], 'Chat eliminado exitosamente', 200);

        } catch (\Exception $e) {
            return ApiResponseService::error(
                'Error al eliminar chat: ' . $e->getMessage(),
                'CHAT_014',
                null,
                500
            );
        }
    }

    // INVITACIONES - Invitar usuario a chat
    #[Route('/invitar/{invited_user_token}', name: 'invitar_usuario_chat', methods: ['POST'])]
    public function invitarUsuarioChat(Request $request, string $invited_user_token): JsonResponse
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

            // Validar user_token del invitador
            if (!isset($data['user_token'])) {
                return ApiResponseService::error('user_token requerido', 'AUTH_003', null, 400);
            }

            // Validar chat_token
            if (!isset($data['chat_token'])) {
                return ApiResponseService::error('chat_token requerido', 'CHAT_002', null, 400);
            }

            $inviterToken = $data['user_token'];
            $chatToken = $data['chat_token'];

            // Obtener usuario invitador por token
            $inviterId = $this->getUserIdByToken($inviterToken);

            if (!$inviterId) {
                return ApiResponseService::error('Token inválido o expirado', 'AUTH_002', null, 401);
            }

            // Obtener usuario invitado por token
            $invitedId = $this->getUserIdByToken($invited_user_token);

            if (!$invitedId) {
                return ApiResponseService::error('Token del usuario invitado inválido o expirado', 'AUTH_008', null, 401);
            }

            // No puede invitarse a sí mismo
            if ($inviterId === $invitedId) {
                return ApiResponseService::error('No puedes invitarte a ti mismo', 'INV_001', null, 400);
            }

            $inviter = $this->entityManager->getRepository(User::class)->find($inviterId);
            $invited = $this->entityManager->getRepository(User::class)->find($invitedId);

            if (!$inviter) {
                return ApiResponseService::error('Usuario invitador no encontrado', 'USER_001', null, 404);
            }

            if (!$invited) {
                return ApiResponseService::error('Usuario invitado no encontrado', 'USER_007', null, 404);
            }

            // Buscar el chat por token
            $chat = $this->entityManager->getRepository(Chats::class)
                ->findOneBy(['chatToken' => $chatToken]);

            if (!$chat) {
                return ApiResponseService::error('Chat no encontrado', 'CHAT_008', null, 404);
            }

            // Verificar que el invitador pertenece al chat
            $inviterInChat = $this->entityManager->getRepository(ChatUsuarios::class)
                ->createQueryBuilder('cu')
                ->where('cu.chat = :chat')
                ->andWhere('cu.usuario = :usuario')
                ->setParameter('chat', $chat)
                ->setParameter('usuario', $inviter)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$inviterInChat) {
                return ApiResponseService::error('No perteneces a este chat', 'CHAT_010', null, 403);
            }

            // Verificar que el invitado no está ya en el chat
            $invitedInChat = $this->entityManager->getRepository(ChatUsuarios::class)
                ->createQueryBuilder('cu')
                ->where('cu.chat = :chat')
                ->andWhere('cu.usuario = :usuario')
                ->setParameter('chat', $chat)
                ->setParameter('usuario', $invited)
                ->getQuery()
                ->getOneOrNullResult();

            if ($invitedInChat) {
                return ApiResponseService::error('El usuario ya está en el chat', 'INV_002', null, 400);
            }

            // Crear clave única para la invitación
            $invitationKey = 'invitation_' . $chatToken . '_' . $invited_user_token;

            // Verificar si ya existe una invitación pendiente
            $existingInvitation = null;
            if (function_exists('apcu_fetch')) {
                $existingInvitation = apcu_fetch($invitationKey);
            }
            if ($existingInvitation === false) {
                $existingInvitation = $this->fileInvitationGet($invitationKey);
            }

            if ($existingInvitation) {
                return ApiResponseService::error('Ya existe una invitación pendiente para este usuario', 'INV_003', null, 400);
            }

            // Crear invitación
            $invitationData = json_encode([
                'inviter_id' => $inviterId,
                'inviter_username' => $inviter->getUsername(),
                'invited_id' => $invitedId,
                'invited_username' => $invited->getUsername(),
                'chat_id' => $chat->getId(),
                'chat_token' => $chatToken,
                'chat_tipo' => $chat->getTipo(),
                'timestamp' => time(),
            ]);

            // Guardar en caché (TTL: 24 horas = 86400 segundos)
            $ttl = 86400;
            if (function_exists('apcu_store')) {
                apcu_store($invitationKey, $invitationData, $ttl);
            }
            // Guardar también en archivo como fallback
            $this->fileInvitationStore($invitationKey, $invitationData, $ttl);

            return ApiResponseService::success([
                'invitacion_key' => $invitationKey,
                'invitador' => [
                    'id' => $inviterId,
                    'username' => $inviter->getUsername(),
                ],
                'invitado' => [
                    'id' => $invitedId,
                    'username' => $invited->getUsername(),
                    'email' => $invited->getEmail(),
                ],
                'chat' => [
                    'id' => $chat->getId(),
                    'token' => $chatToken,
                    'tipo' => $chat->getTipo(),
                ],
                'expira_en' => $ttl . ' segundos (24 horas)',
            ], 'Invitación enviada exitosamente', 201);

        } catch (\Exception $e) {
            return ApiResponseService::error(
                'Error al enviar invitación: ' . $e->getMessage(),
                'INV_004',
                null,
                500
            );
        }
    }

    // INVITACIONES - Rechazar invitación
    #[Route('/invitar/{invitacion_token}/rechazar', name: 'rechazar_invitacion', methods: ['POST'])]
    public function rechazarInvitacion(Request $request, string $invitacion_token): JsonResponse
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

            $userToken = $data['user_token'];

            // Obtener usuario por token
            $userId = $this->getUserIdByToken($userToken);

            if (!$userId) {
                return ApiResponseService::error('Token inválido o expirado', 'AUTH_002', null, 401);
            }

            $usuario = $this->entityManager->getRepository(User::class)->find($userId);

            if (!$usuario) {
                return ApiResponseService::error('Usuario no encontrado', 'USER_001', null, 404);
            }

            // Obtener invitación
            $invitationData = null;
            if (function_exists('apcu_fetch')) {
                $invitationData = apcu_fetch($invitacion_token);
            }
            if ($invitationData === false || $invitationData === null) {
                $invitationData = $this->fileInvitationGet($invitacion_token);
            }

            if (!$invitationData) {
                return ApiResponseService::error('Invitación no encontrada o expirada', 'INV_005', null, 404);
            }

            // Decodificar datos de invitación
            $invitation = json_decode($invitationData, true);

            if (!$invitation) {
                return ApiResponseService::error('Datos de invitación inválidos', 'INV_006', null, 400);
            }

            // Verificar que el usuario es el invitado
            if ($invitation['invited_id'] != $userId) {
                return ApiResponseService::error('No tienes permiso para rechazar esta invitación', 'INV_007', null, 403);
            }

            // Eliminar invitación del caché
            if (function_exists('apcu_delete')) {
                apcu_delete($invitacion_token);
            }
            $this->fileInvitationDelete($invitacion_token);

            return ApiResponseService::success([
                'invitacion_token' => $invitacion_token,
                'invitador' => [
                    'id' => $invitation['inviter_id'],
                    'username' => $invitation['inviter_username'],
                ],
                'invitado' => [
                    'id' => $userId,
                    'username' => $usuario->getUsername(),
                ],
                'chat' => [
                    'token' => $invitation['chat_token'],
                    'tipo' => $invitation['chat_tipo'],
                ],
            ], 'Invitación rechazada exitosamente', 200);

        } catch (\Exception $e) {
            return ApiResponseService::error(
                'Error al rechazar invitación: ' . $e->getMessage(),
                'INV_008',
                null,
                500
            );
        }
    }

    // INVITACIONES - Aceptar invitación
    #[Route('/invitar/{invitacion_token}/aceptar', name: 'aceptar_invitacion', methods: ['POST'])]
    public function aceptarInvitacion(Request $request, string $invitacion_token): JsonResponse
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

            $userToken = $data['user_token'];

            // Obtener usuario por token
            $userId = $this->getUserIdByToken($userToken);

            if (!$userId) {
                return ApiResponseService::error('Token inválido o expirado', 'AUTH_002', null, 401);
            }

            $usuario = $this->entityManager->getRepository(User::class)->find($userId);

            if (!$usuario) {
                return ApiResponseService::error('Usuario no encontrado', 'USER_001', null, 404);
            }

            // Obtener invitación
            $invitationData = null;
            if (function_exists('apcu_fetch')) {
                $invitationData = apcu_fetch($invitacion_token);
            }
            if ($invitationData === false || $invitationData === null) {
                $invitationData = $this->fileInvitationGet($invitacion_token);
            }

            if (!$invitationData) {
                return ApiResponseService::error('Invitación no encontrada o expirada', 'INV_005', null, 404);
            }

            // Decodificar datos de invitación
            $invitation = json_decode($invitationData, true);

            if (!$invitation) {
                return ApiResponseService::error('Datos de invitación inválidos', 'INV_006', null, 400);
            }

            // Verificar que el usuario es el invitado
            if ($invitation['invited_id'] != $userId) {
                return ApiResponseService::error('No tienes permiso para aceptar esta invitación', 'INV_009', null, 403);
            }

            // Buscar el chat
            $chat = $this->entityManager->getRepository(Chats::class)->find($invitation['chat_id']);

            if (!$chat) {
                return ApiResponseService::error('Chat no encontrado', 'CHAT_008', null, 404);
            }

            // Verificar que el usuario no esté ya en el chat
            $yaEnChat = $this->entityManager->getRepository(ChatUsuarios::class)
                ->createQueryBuilder('cu')
                ->where('cu.chat = :chat')
                ->andWhere('cu.usuario = :usuario')
                ->setParameter('chat', $chat)
                ->setParameter('usuario', $usuario)
                ->getQuery()
                ->getOneOrNullResult();

            if ($yaEnChat) {
                // Eliminar invitación del caché
                if (function_exists('apcu_delete')) {
                    apcu_delete($invitacion_token);
                }
                $this->fileInvitationDelete($invitacion_token);

                return ApiResponseService::error('Ya estás en este chat', 'INV_002', null, 400);
            }

            // Agregar usuario al chat
            $chatUsuario = new ChatUsuarios();
            $chatUsuario->setChat($chat);
            $chatUsuario->setUsuario($usuario);
            $chatUsuario->setFechaUnion(new \DateTime());

            $this->entityManager->persist($chatUsuario);
            $this->entityManager->flush();

            // Eliminar invitación del caché
            if (function_exists('apcu_delete')) {
                apcu_delete($invitacion_token);
            }
            $this->fileInvitationDelete($invitacion_token);

            return ApiResponseService::success([
                'invitacion_token' => $invitacion_token,
                'invitador' => [
                    'id' => $invitation['inviter_id'],
                    'username' => $invitation['inviter_username'],
                ],
                'usuario' => [
                    'id' => $userId,
                    'username' => $usuario->getUsername(),
                ],
                'chat' => [
                    'id' => $chat->getId(),
                    'token' => $chat->getChatToken(),
                    'tipo' => $chat->getTipo(),
                ],
                'fecha_union' => $chatUsuario->getFechaUnion()->format('Y-m-d H:i:s'),
            ], 'Te has unido al chat exitosamente', 201);

        } catch (\Exception $e) {
            return ApiResponseService::error(
                'Error al aceptar invitación: ' . $e->getMessage(),
                'INV_010',
                null,
                500
            );
        }
    }

    // INVITACIONES - Cancelar invitación
    #[Route('/invitar/{invitacion_token}', name: 'cancelar_invitacion', methods: ['DELETE'])]
    public function cancelarInvitacion(Request $request, string $invitacion_token): JsonResponse
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

            $userToken = $data['user_token'];

            // Obtener usuario por token
            $userId = $this->getUserIdByToken($userToken);

            if (!$userId) {
                return ApiResponseService::error('Token inválido o expirado', 'AUTH_002', null, 401);
            }

            $usuario = $this->entityManager->getRepository(User::class)->find($userId);

            if (!$usuario) {
                return ApiResponseService::error('Usuario no encontrado', 'USER_001', null, 404);
            }

            // Obtener invitación
            $invitationData = null;
            if (function_exists('apcu_fetch')) {
                $invitationData = apcu_fetch($invitacion_token);
            }
            if ($invitationData === false || $invitationData === null) {
                $invitationData = $this->fileInvitationGet($invitacion_token);
            }

            if (!$invitationData) {
                return ApiResponseService::error('Invitación no encontrada o expirada', 'INV_005', null, 404);
            }

            // Decodificar datos de invitación
            $invitation = json_decode($invitationData, true);

            if (!$invitation) {
                return ApiResponseService::error('Datos de invitación inválidos', 'INV_006', null, 400);
            }

            // Verificar que el usuario es el invitador
            if ($invitation['inviter_id'] != $userId) {
                return ApiResponseService::error('Solo el invitador puede cancelar esta invitación', 'INV_011', null, 403);
            }

            // Eliminar invitación del caché
            if (function_exists('apcu_delete')) {
                apcu_delete($invitacion_token);
            }
            $this->fileInvitationDelete($invitacion_token);

            return ApiResponseService::success([
                'invitacion_token' => $invitacion_token,
                'invitador' => [
                    'id' => $userId,
                    'username' => $usuario->getUsername(),
                ],
                'invitado' => [
                    'id' => $invitation['invited_id'],
                    'username' => $invitation['invited_username'],
                ],
                'chat' => [
                    'token' => $invitation['chat_token'],
                    'tipo' => $invitation['chat_tipo'],
                ],
            ], 'Invitación cancelada exitosamente', 200);

        } catch (\Exception $e) {
            return ApiResponseService::error(
                'Error al cancelar invitación: ' . $e->getMessage(),
                'INV_012',
                null,
                500
            );
        }
    }

    // CHAT GENERAL - Listar usuarios en chat público
    #[Route('/general/usuarios', name: 'general_usuarios', methods: ['POST'])]
    public function listarUsuariosGeneral(Request $request): JsonResponse
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

            $userToken = $data['user_token'];

            // Obtener usuario por token
            $userId = $this->getUserIdByToken($userToken);

            if (!$userId) {
                return ApiResponseService::error('Token inválido o expirado', 'AUTH_002', null, 401);
            }

            $user = $this->entityManager->getRepository(User::class)->find($userId);

            if (!$user) {
                return ApiResponseService::error('Usuario no encontrado', 'USER_001', null, 404);
            }

            // Obtener el chat general
            $chatGeneral = $this->entityManager->getRepository(Chats::class)
                ->findOneBy(['chatToken' => self::CHAT_GENERAL_TOKEN]);
            
            if (!$chatGeneral) {
                return ApiResponseService::success([
                    'usuarios' => [],
                    'total' => 0,
                ], 'Chat general no existe aún', 200);
            }

            // Obtener usuarios en el chat general
            $chatUsuarios = $this->entityManager->getRepository(ChatUsuarios::class)
                ->createQueryBuilder('cu')
                ->where('cu.chat = :chat')
                ->setParameter('chat', $chatGeneral)
                ->getQuery()
                ->getResult();

            $usuarios = [];
            foreach ($chatUsuarios as $chatUsuario) {
                $usuario = $chatUsuario->getUsuario();
                if ($usuario) {
                    $usuarios[] = [
                        'usuario_id' => $usuario->getId(),
                        'username' => $usuario->getUsername(),
                        'email' => $usuario->getEmail(),
                        'en_linea' => $usuario->isEnLinea(),
                        'fecha_union' => $chatUsuario->getFechaUnion() 
                            ? $chatUsuario->getFechaUnion()->format('Y-m-d H:i:s') 
                            : null,
                    ];
                }
            }

            return ApiResponseService::success([
                'usuarios' => $usuarios,
                'total' => count($usuarios),
                'chat_token' => self::CHAT_GENERAL_TOKEN,
            ], 'Usuarios en chat público obtenidos', 200);

        } catch (\Exception $e) {
            return ApiResponseService::error(
                'Error al obtener usuarios: ' . $e->getMessage(),
                'CHAT_005',
                null,
                500
            );
        }
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

    /**
     * Obtiene ruta del archivo de invitaciones
     */
    private function fileInvitationPath(): string
    {
        $dir = dirname(__DIR__, 3) . '/var';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir . '/invitations.json';
    }

    /**
     * Guarda invitación en archivo
     */
    private function fileInvitationStore(string $key, string $data, int $ttl): void
    {
        $path = $this->fileInvitationPath();
        $invitations = [];
        if (file_exists($path)) {
            $contents = @file_get_contents($path);
            $invitations = $contents ? json_decode($contents, true) ?? [] : [];
        }

        // Limpiar invitaciones expiradas
        $now = time();
        foreach ($invitations as $k => $inv) {
            if (isset($inv['expires_at']) && $inv['expires_at'] < $now) {
                unset($invitations[$k]);
            }
        }

        // Agregar nueva invitación
        $invitations[$key] = [
            'data' => $data,
            'expires_at' => time() + $ttl,
        ];

        @file_put_contents($path, json_encode($invitations, JSON_PRETTY_PRINT));
    }

    /**
     * Obtiene invitación desde archivo
     */
    private function fileInvitationGet(string $key): ?string
    {
        $path = $this->fileInvitationPath();
        if (!file_exists($path)) {
            return null;
        }

        $contents = @file_get_contents($path);
        $invitations = $contents ? json_decode($contents, true) ?? [] : [];

        if (isset($invitations[$key])) {
            $inv = $invitations[$key];
            // Verificar si no ha expirado
            if (isset($inv['expires_at']) && $inv['expires_at'] >= time()) {
                return $inv['data'];
            }
        }

        return null;
    }

    /**
     * Elimina invitación del archivo
     */
    private function fileInvitationDelete(string $key): void
    {
        $path = $this->fileInvitationPath();
        if (!file_exists($path)) {
            return;
        }

        $contents = @file_get_contents($path);
        $invitations = $contents ? json_decode($contents, true) ?? [] : [];

        if (isset($invitations[$key])) {
            unset($invitations[$key]);
            @file_put_contents($path, json_encode($invitations, JSON_PRETTY_PRINT));
        }
    }
}
