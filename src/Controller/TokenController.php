<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class TokenController extends AbstractController
{
    #[Route('/api/v1/token/refresh', name: 'api_token_refresh', methods: ['POST'])]
    public function refreshToken(): void
    {
        // return $this->json(['message' => 'Token refreshed'], Response::HTTP_OK);
    }
}