<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\BillingUser;
use OpenApi\Annotations as OA;

class UserController extends AbstractController
{
    /**
     * @OA\Get(
     *     path="/api/v1/users/current",
     *     summary="Get current authenticated user",
     *     description="Returns the details of the currently authenticated user",
     *     @OA\Response(
     *         response=200,
     *         description="User details",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="username", type="string", description="The user's email address"),
     *             @OA\Property(property="roles", type="array", @OA\Items(type="string"), description="The user's roles"),
     *             @OA\Property(property="balance", type="number", format="float", description="The user's balance")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", description="Unauthorized access")
     *         )
     *     ),
     *     security={{ "Bearer":{} }}
     * )
     */
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