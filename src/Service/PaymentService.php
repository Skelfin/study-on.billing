<?php

namespace App\Service;

use App\Entity\BillingUser;
use App\Entity\Course;
use App\Entity\Transaction;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class PaymentService
{
    private EntityManagerInterface $entityManager;
    private TransactionRepository $transactionRepository;
    private LoggerInterface $logger;
    private float $initialDepositAmount;

    public function __construct(
        EntityManagerInterface $entityManager,
        TransactionRepository $transactionRepository,
        LoggerInterface $logger,
        float $initialDepositAmount
    ) {
        $this->entityManager = $entityManager;
        $this->transactionRepository = $transactionRepository;
        $this->logger = $logger;
        $this->initialDepositAmount = $initialDepositAmount;
    }

    /**
     * Пополнение счета пользователя.
     *
     * @param BillingUser $user
     * @param float $amount
     * @return Transaction
     * @throws \Exception
     */
    public function deposit(BillingUser $user, float $amount): Transaction
    {
        $this->entityManager->beginTransaction();

        try {
            $transaction = new Transaction();
            $transaction->setUser($user);
            $transaction->setType(Transaction::TYPE_DEPOSIT);
            $transaction->setAmount($amount);
            $transaction->setCreatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($transaction);

            $user->setBalance($user->getBalance() + $amount);
            $this->entityManager->persist($user);

            $this->entityManager->flush();
            $this->entityManager->commit();

            return $transaction;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Ошибка при пополнении счета: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 
     *
     * @param BillingUser $user
     * @param Course $course
     * @return Transaction
     * @throws \Exception
     */
    public function payCourse(BillingUser $user, Course $course): Transaction
    {
        $this->entityManager->beginTransaction();

        try {
            $coursePrice = $course->getPrice();

            if ($user->getBalance() < $coursePrice) {
                throw new \Exception('Недостаточно средств для оплаты курса.');
            }

            $existingTransaction = $this->transactionRepository->findOneBy([
                'user' => $user,
                'course' => $course,
                'type' => Transaction::TYPE_PAYMENT,
            ]);

            if ($existingTransaction) {
                throw new \Exception('Курс уже оплачен.');
            }

            $transaction = new Transaction();
            $transaction->setUser($user);
            $transaction->setCourse($course);
            $transaction->setType(Transaction::TYPE_PAYMENT);
            $transaction->setAmount($coursePrice);
            $transaction->setCreatedAt(new \DateTimeImmutable());

            if ($course->getTypeName() === 'rent') {
                $expiresAt = (new \DateTimeImmutable())->modify('+1 week');
                $transaction->setExpiresAt($expiresAt);
            }

            $this->entityManager->persist($transaction);

            $user->setBalance($user->getBalance() - $coursePrice);
            $this->entityManager->persist($user);

            $this->entityManager->flush();
            $this->entityManager->commit();

            return $transaction;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Ошибка при оплате курса: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 
     *
     * @param BillingUser $user
     * @return Transaction
     * @throws \Exception
     */
    public function initializeUserBalance(BillingUser $user): Transaction
    {
        return $this->deposit($user, $this->initialDepositAmount);
    }
}