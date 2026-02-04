<?php

namespace App\Controller\Api;

use App\Service\ApiResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class ConexionController extends AbstractController
{
    #[Route('/conexion', name: 'conexion', methods: ['POST'])]
    public function getConexion(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validar API_KEY
        if (!isset($data['apikey']) || $data['apikey'] !== $_ENV['API_KEY']) {
            return ApiResponseService::error('API key inválida o no proporcionada', 'AUTH_009', null, 401);
        }

        return ApiResponseService::success(
            ['status' => 'conectado'],
            'Conexión exitosa',
            200
        );
    }
}
