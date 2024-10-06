<?php

namespace App\Controller;

use App\Entity\Course;
use App\Repository\CourseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class CourseController extends AbstractController
{
    #[Route('/api/v1/courses', name: 'api_courses_list', methods: ['GET'])]
    public function listCourses(CourseRepository $courseRepository): JsonResponse
    {
        $courses = $courseRepository->findAll();

        $response = array_map(function (Course $course) {
            $courseData = [
                'code' => $course->getCode(),
                'type' => $course->getTypeName(),
            ];
            if ($course->getPrice() !== 0.00) {
                $courseData['price'] = number_format($course->getPrice(), 2, '.', '');
            }
            return $courseData;
        }, $courses);

        return $this->json($response);
    }

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
            'price' => $course->getPrice() !== null ? number_format($course->getPrice(), 2, '.', '') : null,
        ];

        return $this->json($courseData);
    }

    #[Route('/api/v1/courses/new', name: 'api_course_create', methods: ['POST'])]
    public function createCourse(Request $request, EntityManagerInterface $entityManager, CourseRepository $courseRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $validationError = $this->validateCourseData($data, $courseRepository);
        if ($validationError) {
            return $this->json($validationError, Response::HTTP_BAD_REQUEST);
        }

        $course = $this->mapDataToCourse(new Course(), $data, true);
        $entityManager->persist($course);
        $entityManager->flush();

        return $this->json(['success' => true], Response::HTTP_CREATED);
    }

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

        $validationError = $this->validateCourseData($data, $courseRepository, $course);
        if ($validationError) {
            return $this->json($validationError, Response::HTTP_BAD_REQUEST);
        }
        $this->mapDataToCourse($course, $data, false);
        $entityManager->persist($course);
        $entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/api/v1/courses/{code}/delete', name: 'api_course_delete', methods: ['DELETE'])]
    public function deleteCourse(string $code, CourseRepository $courseRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $course = $courseRepository->findOneBy(['code' => $code]);

        if (!$course) {
            return $this->json(['code' => Response::HTTP_NOT_FOUND, 'message' => 'Курс не найден'], Response::HTTP_NOT_FOUND);
        }

        $entityManager->remove($course);
        $entityManager->flush();

        return $this->json(['success' => true]);
    }

    private function validateCourseData(array $data, CourseRepository $courseRepository, ?Course $existingCourse = null): ?array
    {
        $expectedParams = ['type', 'name', 'price'];

        if (!$existingCourse) {
            $expectedParams[] = 'code';
        }

        foreach ($expectedParams as $param) {
            if (!isset($data[$param])) {
                return [
                    'code' => Response::HTTP_BAD_REQUEST,
                    'message' => 'Необходимые параметры: ' . implode(', ', $expectedParams)
                ];
            }
        }

        $typeMapping = [
            'rent' => Course::TYPE_RENT,
            'buy'  => Course::TYPE_BUY,
            'free' => Course::TYPE_FREE,
        ];

        if (!isset($typeMapping[$data['type']])) {
            return [
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Неверный тип курса. Допустимые значения: rent, free, buy'
            ];
        }

        if (!is_numeric($data['price']) || $data['price'] < 0) {
            return [
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Цена должна быть неотрицательным числом'
            ];
        }

        $price = floatval($data['price']);
        $type = $typeMapping[$data['type']];

        if ($type === Course::TYPE_FREE && $price != 0) {
            return [
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Цена должна быть 0 для бесплатного курса'
            ];
        }

        if (($type === Course::TYPE_RENT || $type === Course::TYPE_BUY) && $price <= 0) {
            return [
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Цена должна быть больше 0 для платных курсов'
            ];
        }

        if (!$existingCourse && $courseRepository->findOneBy(['code' => $data['code']])) {
            return [
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Курс с таким кодом уже существует'
            ];
        }

        return null;
    }


    private function mapDataToCourse(Course $course, array $data, bool $updateCode = false): Course
    {
        $typeMapping = [
            'rent' => Course::TYPE_RENT,
            'buy'  => Course::TYPE_BUY,
            'free' => Course::TYPE_FREE,
        ];

        $course->setType($typeMapping[$data['type']]);
        $course->setName($data['name']);

        if ($updateCode && isset($data['code'])) {
            $course->setCode($data['code']);
        }

        $course->setPrice(floatval($data['price']));

        return $course;
    }
}