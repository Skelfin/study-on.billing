<?php

namespace App\Service;

use App\Entity\BillingUser;
use App\Entity\Course;
use App\Entity\Transaction;
use App\Exception\InsufficientFundsException;
use App\Exception\CourseAlreadyPurchasedException;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;

class PaymentService
{
    private EntityManagerInterface $entityManager;
    private TransactionRepository $transactionRepository;
    private float $initialDepositAmount;

    public function __construct(
        EntityManagerInterface $entityManager,
        TransactionRepository $transactionRepository,
        float $initialDepositAmount
    ) {
        $this->entityManager = $entityManager;
        $this->transactionRepository = $transactionRepository;
        $this->initialDepositAmount = $initialDepositAmount;
    }

    /**
     * Пополнение баланса пользователя
     * @param BillingUser $user
     * @param float $amount
     * @return Transaction
     */
    public function deposit(BillingUser $user, float $amount): Transaction
    {
        return $this->processTransaction(function () use ($user, $amount) {
            $transaction = $this->createAndPersistTransaction($user, $amount, Transaction::TYPE_DEPOSIT);
            $user->setBalance($user->getBalance() + $amount);
            $this->entityManager->persist($user);

            return $transaction;
        });
    }

    /**
     * Оплата курса
     * @param BillingUser $user
     * @param Course $course
     * @return Transaction
     * @throws InsufficientFundsException
     * @throws CourseAlreadyPurchasedException
     */
    public function payCourse(BillingUser $user, Course $course): Transaction
    {
        return $this->processTransaction(function () use ($user, $course) {
            $coursePrice = $course->getPrice();

            if ($user->getBalance() < $coursePrice) {
                throw new InsufficientFundsException('Недостаточно средств для оплаты курса.');
            }

            if ($this->isCourseAlreadyPurchased($user, $course)) {
                throw new CourseAlreadyPurchasedException('Курс уже оплачен.');
            }

            $transaction = $this->createAndPersistTransaction($user, $coursePrice, Transaction::TYPE_PAYMENT, $course);

            if ($course->getTypeName() === 'rent') {
                $transaction->setExpiresAt((new \DateTimeImmutable())->modify('+1 week'));
            }

            $user->setBalance($user->getBalance() - $coursePrice);
            $this->entityManager->persist($user);

            return $transaction;
        });
    }

    /**
     * Инициализация баланса пользователя
     *
     * @param BillingUser $user
     * @return Transaction
     */
    public function initializeUserBalance(BillingUser $user): Transaction
    {
        return $this->deposit($user, $this->initialDepositAmount);
    }

    /**
     * Общая логика транзакций
     *
     * @param callable $operation
     * @return Transaction
     */
    private function processTransaction(callable $operation): Transaction
    {
        $this->entityManager->beginTransaction();

        try {
            $transaction = $operation();
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $transaction;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * Проверка, был ли курс уже оплачен пользователем.
     *
     * @param BillingUser $user
     * @param Course $course
     * @return bool
     */
    private function isCourseAlreadyPurchased(BillingUser $user, Course $course): bool
    {
        return (bool) $this->transactionRepository->findOneBy([
            'user' => $user,
            'course' => $course,
            'type' => Transaction::TYPE_PAYMENT,
        ]);
    }
    /**
     * Создание и сохранение транзакции.
     *
     * @param BillingUser $user
     * @param float $amount
     * @param string $type
     * @param Course|null $course
     * @return Transaction
     */
    private function createAndPersistTransaction(BillingUser $user, float $amount, string $type, ?Course $course = null): Transaction
    {
        $transaction = new Transaction();
        $transaction->setUser($user);
        $transaction->setType($type);
        $transaction->setAmount($amount);
        $transaction->setCreatedAt(new \DateTimeImmutable());

        if ($course !== null) {
            $transaction->setCourse($course);
        }

        $this->entityManager->persist($transaction);

        return $transaction;
    }
}