<?php

namespace App\Controller;

use App\Entity\Course;
use App\Repository\CourseRepository;
use App\Entity\BillingUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use OpenApi\Annotations as OA;
use App\Service\PaymentService;

class CourseController extends AbstractController
{

    private PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/courses",
     *     summary="Получение списка всех курсов",
     *     description="Возвращает список всех курсов с информацией о стоимости и типе",
     *     @OA\Response(
     *         response=200,
     *         description="Список курсов",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="code", type="string"),
     *                 @OA\Property(property="type", type="string"),
     *                 @OA\Property(property="price", type="number", format="float", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    #[Route('/api/v1/courses', name: 'api_courses_list', methods: ['GET'])]
    public function listCourses(CourseRepository $courseRepository): JsonResponse
    {
        $courses = $courseRepository->findAll();

        $response = [];
        foreach ($courses as $course) {
            $courseData = [
                'code' => $course->getCode(),
                'type' => $course->getTypeName(),
            ];
            if ($course->getPrice() !== 0.00) {
                $courseData['price'] = number_format($course->getPrice(), 2, '.', '');
            }
            $response[] = $courseData;
        }

        return $this->json($response);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/courses/{code}",
     *     summary="Получение информации о курсе",
     *     description="Возвращает информацию о курсе по его коду",
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         description="Символьный код курса",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Информация о курсе",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="type", type="string"),
     *             @OA\Property(property="price", type="number", format="float", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Курс не найден",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    #[Route('/api/v1/courses/{code}', name: 'api_course_get', methods: ['GET'])]
    public function getCourse(string $code, CourseRepository $courseRepository): JsonResponse
    {
        $course = $courseRepository->findOneBy(['code' => $code]);

        if (!$course) {
            return $this->json(['error' => 'Курс не найден'], Response::HTTP_NOT_FOUND);
        }

        $courseData = [
            'code' => $course->getCode(),
            'type' => $course->getTypeName(),
        ];
        if ($course->getPrice() !== null) {
            $courseData['price'] = number_format($course->getPrice(), 2, '.', '');
        }

        return $this->json($courseData);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/courses/{code}/pay",
     *     summary="Оплата курса",
     *     description="Позволяет пользователю оплатить курс",
     *     security={{ "Bearer":{} }},
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         description="Символьный код курса",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Успешная оплата",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="course_type", type="string"),
     *             @OA\Property(property="expires_at", type="string", format="date-time", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Ошибка оплаты",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="code", type="integer"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    #[Route('/api/v1/courses/{code}/pay', name: 'api_course_pay', methods: ['POST'])]
    public function payCourse(
        string $code,
        CourseRepository $courseRepository
    ): JsonResponse {
        /** @var BillingUser $user */
        $user = $this->getUser();

        $course = $courseRepository->findOneBy(['code' => $code]);

        if (!$course) {
            return $this->json(['code' => 404, 'message' => 'Курс не найден'], Response::HTTP_NOT_FOUND);
        }

        try {
            $transaction = $this->paymentService->payCourse($user, $course);

            $response = [
                'success' => true,
                'course_type' => $transaction->getCourse()->getTypeName(),
            ];

            if ($transaction->getExpiresAt()) {
                $response['expires_at'] = $transaction->getExpiresAt()->format(\DateTimeInterface::ATOM);
            }

            return $this->json($response);
        } catch (\Exception $e) {
            $statusCode = Response::HTTP_BAD_REQUEST;
            if ($e->getMessage() === 'Недостаточно средств для оплаты курса.') {
                $statusCode = Response::HTTP_NOT_ACCEPTABLE;
            }

            return $this->json([
                'code' => $statusCode,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/courses/new",
     *     summary="Создание курса",
     *     description="Позволяет создать новый курс (только для пользователей с ролью ROLE_SUPER_ADMIN)",
     *     security={{ "Bearer":{} }},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type", "name", "code", "price"},
     *             @OA\Property(property="type", type="string", enum={"rent", "free", "buy"}),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="price", type="number", format="float")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Курс успешно создан",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Ошибка при создании курса",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="code", type="integer"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    #[Route('/api/v1/courses/new', name: 'api_course_create', methods: ['POST'])]
    public function createCourse(
        Request $request,
        EntityManagerInterface $entityManager,
        CourseRepository $courseRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['type'], $data['name'], $data['code'], $data['price'])) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Необходимые параметры: type, name, code, price'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Проверка 'type'
        $typeMapping = [
            'rent' => Course::TYPE_RENT,
            'buy'  => Course::TYPE_BUY,
            'free' => Course::TYPE_FREE,
        ];

        if (!isset($typeMapping[$data['type']])) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Неверный тип курса. Допустимые значения: rent, free, buy'
            ], Response::HTTP_BAD_REQUEST);
        }
        $type = $typeMapping[$data['type']];

        if (!is_numeric($data['price']) || $data['price'] < 0) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Цена должна быть неотрицательным числом'
            ], Response::HTTP_BAD_REQUEST);
        }
        $price = floatval($data['price']);

        if ($type === Course::TYPE_FREE && $price != 0) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Цена должна быть 0 для бесплатного курса'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (($type === Course::TYPE_RENT || $type === Course::TYPE_BUY) && $price <= 0) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Цена должна быть больше 0 для платных курсов'
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($courseRepository->findOneBy(['code' => $data['code']])) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Курс с таким кодом уже существует'
            ], Response::HTTP_BAD_REQUEST);
        }

        $course = new Course();
        $course->setType($type);
        $course->setName($data['name']);
        $course->setCode($data['code']);
        $course->setPrice($price);

        $entityManager->persist($course);
        $entityManager->flush();

        return $this->json(['success' => true], Response::HTTP_CREATED);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/courses/{code}/edit",
     *     summary="Редактирование курса",
     *     description="Позволяет редактировать существующий курс (только для пользователей с ролью ROLE_SUPER_ADMIN)",
     *     security={{ "Bearer":{} }},
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         description="Символьный код курса",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type", "name", "price"},
     *             @OA\Property(property="type", type="string", enum={"rent", "free", "buy"}),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="price", type="number", format="float")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Курс успешно отредактирован",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Ошибка при редактировании курса",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="code", type="integer"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Курс не найден",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    #[Route('/api/v1/courses/{code}/edit', name: 'api_course_edit', methods: ['POST'])]
    public function editCourse(
        string $code,
        Request $request,
        CourseRepository $courseRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $course = $courseRepository->findOneBy(['code' => $code]);

        if (!$course) {
            return $this->json([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'Курс не найден'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['type'], $data['name'], $data['price'])) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Необходимые параметры: type, name, price'
            ], Response::HTTP_BAD_REQUEST);
        }

        $typeMapping = [
            'rent' => Course::TYPE_RENT,
            'buy'  => Course::TYPE_BUY,
            'free' => Course::TYPE_FREE,
        ];

        if (!isset($typeMapping[$data['type']])) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Неверный тип курса. Допустимые значения: rent, free, buy'
            ], Response::HTTP_BAD_REQUEST);
        }
        $type = $typeMapping[$data['type']];

        if (!is_numeric($data['price']) || $data['price'] < 0) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Цена должна быть неотрицательным числом'
            ], Response::HTTP_BAD_REQUEST);
        }
        $price = floatval($data['price']);

        if ($type === Course::TYPE_FREE && $price != 0) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Цена должна быть 0 для бесплатного курса'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (($type === Course::TYPE_RENT || $type === Course::TYPE_BUY) && $price <= 0) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Цена должна быть больше 0 для платных курсов'
            ], Response::HTTP_BAD_REQUEST);
        }

        $course->setType($type);
        $course->setName($data['name']);
        $course->setPrice($price);

        $entityManager->persist($course);
        $entityManager->flush();

        return $this->json(['success' => true]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/courses/{code}/delete",
     *     summary="Удаление курса",
     *     description="Позволяет удалить существующий курс (только для пользователей с ролью ROLE_SUPER_ADMIN)",
     *     security={{ "Bearer":{} }},
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         description="Символьный код курса",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Курс успешно удалён",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Ошибка при удалении курса",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="code", type="integer"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Курс не найден",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    #[Route('/api/v1/courses/{code}/delete', name: 'api_course_delete', methods: ['DELETE'])]
    public function deleteCourse(
        string $code,
        CourseRepository $courseRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $course = $courseRepository->findOneBy(['code' => $code]);

        if (!$course) {
            return $this->json([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'Курс не найден'
            ], Response::HTTP_NOT_FOUND);
        }

        $entityManager->remove($course);
        $entityManager->flush();

        return $this->json(['success' => true]);
    }
}