<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use App\Entity\BillingUser;
use App\Repository\TransactionRepository;
use App\Service\PaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use OpenApi\Annotations as OA;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class TransactionController extends AbstractController
{
    private PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/transactions",
     *     summary="Получение истории транзакций пользователя",
     *     description="Возвращает историю начислений и списаний текущего пользователя с возможностью фильтрации",
     *     security={{ "Bearer":{} }},
     *     @OA\Parameter(
     *         name="filter[type]",
     *         in="query",
     *         description="Тип транзакции: payment|deposit",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="filter[course_code]",
     *         in="query",
     *         description="Символьный код курса",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="filter[skip_expired]",
     *         in="query",
     *         description="Отбросить истекшие транзакции (true|false)",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="История транзакций",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="type", type="string"),
     *                 @OA\Property(property="course_code", type="string", nullable=true),
     *                 @OA\Property(property="amount", type="number", format="float"),
     *                 @OA\Property(property="expires_at", type="string", format="date-time", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    #[Route('/api/v1/transactions', name: 'api_transactions_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function listTransactions(Request $request, TransactionRepository $transactionRepository): JsonResponse
    {
        /** @var BillingUser $user */
        $user = $this->getUser();

        $filters = $request->query->get('filter');
        if (!is_array($filters)) {
            $filters = [];
        }

        // Приведение skip_expired к булевому типу
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

    /**
     * @OA\Post(
     *     path="/api/v1/transactions/deposit",
     *     summary="Пополнение счета пользователя",
     *     description="Позволяет пользователю пополнить свой счет",
     *     security={{ "Bearer":{} }},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="amount", type="number", format="float")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Успешное пополнение",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="transaction_id", type="integer"),
     *             @OA\Property(property="amount", type="number", format="float"),
     *             @OA\Property(property="balance", type="number", format="float")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Ошибка пополнения",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    #[Route('/api/v1/transactions/deposit', name: 'api_transactions_deposit', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
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
}