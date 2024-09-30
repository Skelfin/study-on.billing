<?php

namespace App\Repository;

use App\Entity\BillingUser;
use App\Entity\Transaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TransactionRepository extends ServiceEntityRepository
{
    public const TYPE_PAYMENT = 'payment';
    public const TYPE_DEPOSIT = 'deposit';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * @param BillingUser $user
     * @param array $filters
     * @return Transaction[]
     */
    public function findByUserWithFilters(BillingUser $user, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC');

        if (isset($filters['type'])) {
            $qb->andWhere('t.type = :type')
                ->setParameter('type', $filters['type']);
        }

        if (isset($filters['course_code'])) {
            $qb->join('t.course', 'c')
                ->andWhere('c.code = :course_code')
                ->setParameter('course_code', $filters['course_code']);
        }

        if (isset($filters['skip_expired']) && $filters['skip_expired']) {
            $qb->andWhere('t.expiresAt IS NULL OR t.expiresAt > :now')
                ->setParameter('now', new \DateTimeImmutable());
        }

        return $qb->getQuery()->getResult();
    }

    //    /**
    //     * @return Transaction[] Returns an array of Transaction objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Transaction
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}