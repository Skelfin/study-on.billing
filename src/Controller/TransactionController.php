<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use App\Entity\BillingUser;
use App\Repository\CourseRepository;
use App\Repository\TransactionRepository;
use App\Service\PaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class TransactionController extends AbstractController
{
    private PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    #[Route('/api/v1/transactions', name: 'api_transactions_list', methods: ['GET'])]
    public function listTransactions(Request $request, TransactionRepository $transactionRepository): JsonResponse
    {
        /** @var BillingUser $user */
        $user = $this->getUser();

        $filters = $request->query->get('filter');
        if (!is_array($filters)) {
            $filters = [];
        }

        if (isset($filters['skip_expired'])) {
            $filters['skip_expired'] = filter_var($filters['skip_expired'], FILTER_VALIDATE_BOOLEAN);
        }

        $transactions = $transactionRepository->findByUserWithFilters($user, $filters);

        $response = [];
        foreach ($transactions as $transaction) {
            $transactionData = [
                'id' => $transaction->getId(),
                'created_at' => $transaction->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'type' => $transaction->getTypeName(),
                'amount' => number_format($transaction->getAmount(), 2, '.', ''),
            ];
            if ($transaction->getCourse()) {
                $transactionData['course_code'] = $transaction->getCourse()->getCode();
            }
            if ($transaction->getExpiresAt()) {
                $transactionData['expires_at'] = $transaction->getExpiresAt()->format(\DateTimeInterface::ATOM);
            }
            $response[] = $transactionData;
        }

        return $this->json($response);
    }

    #[Route('/api/v1/transactions/deposit', name: 'api_transactions_deposit', methods: ['POST'])]
    public function deposit(Request $request): JsonResponse
    {
        /** @var BillingUser $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $amount = $data['amount'] ?? null;

        if ($amount === null || !is_numeric($amount) || $amount <= 0) {
            return $this->json(['error' => 'Неверная сумма пополнения.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $transaction = $this->paymentService->deposit($user, (float)$amount);

            return $this->json([
                'transaction_id' => $transaction->getId(),
                'amount' => number_format($transaction->getAmount(), 2, '.', ''),
                'balance' => number_format($user->getBalance(), 2, '.', ''),
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Ошибка при пополнении счета: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

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
}