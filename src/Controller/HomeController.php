<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/api', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    #[Route('/api/docs', name: 'api_docs', methods: ['GET'])]
    public function apiDocs(): JsonResponse
    {
        $endpoints = [
            'AUTENTICACIÓN' => [
                [
                    'método' => 'POST',
                    'ruta' => '/api/conexion',
                    'descripción' => 'Verificar conexión exitosa con la API',
                    'público' => true,
                    'body' => ['apikey' => 'string'],
                ],
                [
                    'método' => 'POST',
                    'ruta' => '/api/login',
                    'descripción' => 'Iniciar sesión',
                    'público' => true,
                    'body' => ['email' => 'string', 'password' => 'string', 'apikey' => 'string'],
                ],
                [
                    'método' => 'POST',
                    'ruta' => '/api/registro',
                    'descripción' => 'Registrar nuevo usuario',
                    'público' => true,
                    'body' => ['email' => 'string', 'username' => 'string', 'password' => 'string', 'apikey' => 'string'],
                ],
                [
                    'método' => 'POST',
                    'ruta' => '/api/logout',
                    'descripción' => 'Cerrar sesión',
                    'público' => false,
                    'body' => ['api_key' => 'string', 'token_user' => 'string'],
                ],
            ],
            'USUARIOS' => [
                [
                    'método' => 'POST',
                    'ruta' => '/api/usuarios/perfil',
                    'descripción' => 'Obtener mi perfil',
                    'público' => false,
                    'body' => ['api_key' => 'string', 'token_user' => 'string'],
                ],
                [
                    'método' => 'PUT',
                    'ruta' => '/api/usuarios/perfil',
                    'descripción' => 'Actualizar mi perfil',
                    'público' => false,
                    'body' => ['api_key' => 'string', 'token_user' => 'string', 'username' => 'string (opcional)', 'email' => 'string (opcional)', 'password' => 'string (opcional)', 'latitud' => 'float (opcional)', 'longitud' => 'float (opcional)', 'en_linea' => 'boolean (opcional)'],
                ],
                [
                    'método' => 'GET/POST',
                    'ruta' => '/api/usuarios',
                    'descripción' => 'Listar usuarios cercanos en un radio de 0-5 km',
                    'público' => false,
                    'body' => ['api_key' => 'string', 'token_user' => 'string', 'lat' => 'float (opcional)', 'lon' => 'float (opcional)', 'page' => 'int (opcional)', 'per_page' => 'int (opcional)'],
                ],
                [
                    'método' => 'POST',
                    'ruta' => '/api/usuarios/{token}',
                    'descripción' => 'Obtener usuario por token',
                    'público' => false,
                    'body' => ['api_key' => 'string', 'token_user' => 'string'],
                ],
                [
                    'método' => 'DELETE',
                    'ruta' => '/api/usuarios/{token}',
                    'descripción' => 'Eliminar usuario por token',
                    'público' => false,
                    'body' => ['api_key' => 'string', 'token_user' => 'string'],
                ],
            ],
            'BLOQUEOS' => [
                [
                    'método' => 'POST',
                    'ruta' => '/api/bloqueo/bloquear',
                    'descripción' => 'Bloquear un usuario',
                    'público' => false,
                    'body' => ['api_key' => 'string', 'token_user' => 'string', 'usuario_bloquear_token' => 'string'],
                ],
                [
                    'método' => 'DELETE',
                    'ruta' => '/api/bloqueo/desbloquear',
                    'descripción' => 'Desbloquear un usuario',
                    'público' => false,
                    'body' => ['api_key' => 'string', 'token_user' => 'string', 'user_token' => 'string'],
                ],
                [
                    'método' => 'POST',
                    'ruta' => '/api/bloqueados',
                    'descripción' => 'Listar usuarios bloqueados',
                    'público' => false,
                    'body' => ['api_key' => 'string', 'token_user' => 'string'],
                ],
            ],
            'ACTUALIZACIÓN' => [
                [
                    'método' => 'POST',
                    'ruta' => '/api/actualizar',
                    'descripción' => 'Actualiza la ubicación del usuario y obtiene cambios recientes',
                    'público' => false,
                    'body' => ['api_key' => 'string', 'user_token' => 'string', 'token_sala' => 'string', 'ultimo_mensaje_id' => 'int', 'latitud' => 'float (opcional)', 'longitud' => 'float (opcional)', 'ultima_actualizacion' => 'string datetime (opcional)'],
                ],
            ],
            'CHAT GENERAL' => [
                [
                    'método' => 'POST',
                    'ruta' => '/api/general/mensaje',
                    'descripción' => 'Enviar mensaje al chat general',
                    'público' => false,
                    'body' => ['api_key' => 'string', 'token_user' => 'string', 'mensaje' => 'string'],
                ],
                [
                    'método' => 'POST',
                    'ruta' => '/api/general/usuarios',
                    'descripción' => 'Obtener usuarios conectados al chat general',
                    'público' => false,
                    'body' => ['api_key' => 'string', 'token_user' => 'string'],
                ],
            ],
            'CHAT PRIVADO' => [
                [
                    'método' => 'POST',
                    'ruta' => '/api/privado',
                    'descripción' => 'Listar chats privados del usuario',
                    'público' => false,
                    'body' => ['api_key' => 'string', 'token_user' => 'string'],
                ],
                [
                    'método' => 'POST',
                    'ruta' => '/api/privado/{token_chat}',
                    'descripción' => 'Obtener mensajes de un chat privado',
                    'público' => false,
                    'body' => ['api_key' => 'string', 'token_user' => 'string'],
                ],
                [
                    'método' => 'POST',
                    'ruta' => '/api/privado/{chat_token}/mensaje',
                    'descripción' => 'Enviar mensaje a un chat privado',
                    'público' => false,
                    'body' => ['api_key' => 'string', 'token_user' => 'string', 'mensaje' => 'string'],
                ],
                [
                    'método' => 'POST',
                    'ruta' => '/api/privado/{chat_token}/salir',
                    'descripción' => 'Salir de un chat privado',
                    'público' => false,
                    'body' => ['api_key' => 'string', 'token_user' => 'string'],
                ],
                [
                    'método' => 'DELETE',
                    'ruta' => '/api/privado/{chat_token}',
                    'descripción' => 'Eliminar un chat privado',
                    'público' => false,
                    'body' => ['api_key' => 'string', 'token_user' => 'string'],
                ],
            ],
            'INVITACIONES' => [
                [
                    'método' => 'POST',
                    'ruta' => '/api/invitar/{user_token}',
                    'descripción' => 'Invitar usuario a chat privado',
                    'público' => false,
                    'body' => ['api_key' => 'string', 'token_user' => 'string'],
                ],
                [
                    'método' => 'POST',
                    'ruta' => '/api/invitar/{invitacion_token}/rechazar',
                    'descripción' => 'Rechazar invitación a chat privado',
                    'público' => false,
                    'body' => ['api_key' => 'string', 'token_user' => 'string'],
                ],
                [
                    'método' => 'POST',
                    'ruta' => '/api/invitar/{invitacion_token}/aceptar',
                    'descripción' => 'Aceptar invitación a chat privado',
                    'público' => false,
                    'body' => ['api_key' => 'string', 'token_user' => 'string'],
                ],
                [
                    'método' => 'DELETE',
                    'ruta' => '/api/invitar/{invitacion_token}',
                    'descripción' => 'Cancelar invitación a chat privado',
                    'público' => false,
                    'body' => ['api_key' => 'string', 'token_user' => 'string'],
                ],
            ],
        ];

        return new JsonResponse([
            'success' => true,
            'message' => 'Documentación de API',
            'data' => [
                'endpoints' => $endpoints,
                'base_url' => 'http://localhost/RedSocial/public',
                'autenticación' => 'La mayoría de endpoints requieren api_key y token_user en el body',
            ],
        ]);
    }
}
