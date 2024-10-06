<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\BillingUser;

class UserController extends AbstractController
{
    #[Route('/api/v1/users/current', name: 'api_user_current', methods: ['GET'])]
    public function currentUser(): Response
    {
        /** @var BillingUser $user */
        $user = $this->getUser();

        return $this->json([
            'username' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'balance' => $user->getBalance(),
        ]);
    }
}