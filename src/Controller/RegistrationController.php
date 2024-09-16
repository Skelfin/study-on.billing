<?php

namespace App\Controller;

use App\Entity\BillingUser;
use App\Dto\UserDto;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use OpenApi\Annotations as OA;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;

class RegistrationController extends AbstractController
{
    private JWTTokenManagerInterface $jwtManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(
        JWTTokenManagerInterface $jwtManager,
        UserPasswordHasherInterface $passwordHasher
    ) {
        $this->jwtManager = $jwtManager;
        $this->passwordHasher = $passwordHasher;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/register",
     *     description="Регистрация нового пользователя",
     *     @OA\RequestBody(
     *         required=true,
     *         content={
     *             @OA\MediaType(
     *                 mediaType="application/json",
     *                 @OA\Schema(
     *                     type="object",
     *                     @OA\Property(property="email", type="string", example="user@example.com"),
     *                     @OA\Property(property="password", type="string", example="password123")
     *                 )
     *             )
     *         }
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Пользователь успешно зарегистрирован",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="token", type="string", example="jwt_token_here")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Ошибка валидации",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Email уже используется",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    #[Route('/api/v1/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager,
        RefreshTokenGeneratorInterface $refreshTokenGenerator,
        RefreshTokenManagerInterface $refreshTokenManager
    ): Response {
        $userDto = $serializer->deserialize($request->getContent(), UserDto::class, 'json');
        $errors = $validator->validate($userDto);

        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $existingUser = $entityManager->getRepository(BillingUser::class)->findOneBy(['email' => $userDto->email]);
        if ($existingUser) {
            return $this->json(['error' => 'Этот email уже используется.'], Response::HTTP_CONFLICT);
        }

        $user = BillingUser::fromDto($userDto, $this->passwordHasher);

        $entityManager->persist($user);
        $entityManager->flush();

        // Создание JWT-токена
        $token = $this->jwtManager->create($user);

        // Создание refresh-токена
        $refreshToken = $refreshTokenGenerator->createForUserWithTtl(
            $user,
            (new \DateTime())->modify('+1 month')->getTimestamp()
        );
        $refreshTokenManager->save($refreshToken);

        return $this->json([
            'token' => $token,
            'refresh_token' => $refreshToken->getRefreshToken(),
        ], Response::HTTP_CREATED);
    }
}