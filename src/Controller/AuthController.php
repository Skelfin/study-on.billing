<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Annotations as OA;

class AuthController extends AbstractController
{
    /**
     * @Route("/api/v1/auth", methods={"POST"})
     * @OA\Post(
     *     path="/api/v1/auth",
     *     summary="Авторизация пользователя",
     *     @OA\RequestBody(
     *         required=true,
     *         content={
     *             @OA\MediaType(
     *                 mediaType="application/json",
     *                 @OA\Schema(
     *                     type="object",
     *                     @OA\Property(
     *                         property="email",
     *                         type="string",
     *                         example="user@example.com"
     *                     ),
     *                     @OA\Property(
     *                         property="password",
     *                         type="string",
     *                         example="password123"
     *                     )
     *                 )
     *             )
     *         }
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Успешная авторизация",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="token", type="string", example="jwt_token_here")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Неверные учетные данные",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    #[Route('/api/v1/auth', name: 'api_auth', methods: ['POST'])]
    public function login(): void
    {
        throw new \Exception('Этот метод может быть пустым - его перехватит система json_login.');
    }
}