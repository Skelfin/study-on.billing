<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;
use Symfony\Component\Mime\Email;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Entity\Transaction;
use App\Entity\Course;
use App\Entity\BillingUser;

#[AsCommand(
    name: 'payment:report',
    description: 'Генерация и отправка индивидуальных отчетов об оплаченных курсах пользователям',
)]
class PaymentReportCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private MailerInterface $mailer;
    private Environment $twig;

    public function __construct(EntityManagerInterface $entityManager, MailerInterface $mailer, Environment $twig)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
        $this->twig = $twig;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->writeln('Начало генерации отчетов об оплаченных курсах для каждого пользователя...');

        $startDate = (new \DateTime('first day of previous month'))->setTime(0, 0, 0);
        $endDate = (new \DateTime('last day of previous month'))->setTime(23, 59, 59);

        $io->writeln(sprintf('Период отчетов: %s - %s', $startDate->format('d.m.Y'), $endDate->format('d.m.Y')));

        $userRepository = $this->entityManager->getRepository(BillingUser::class);
        $users = $userRepository->findAll();

        if (empty($users)) {
            $io->writeln('Нет пользователей в системе.');
            return Command::SUCCESS;
        }

        $courseTypeNames = [
            Course::TYPE_RENT => 'Аренда',
            Course::TYPE_BUY => 'Покупка',
            Course::TYPE_FREE => 'Бесплатный',
        ];

        foreach ($users as $user) {
            /** @var BillingUser $user */
            $userEmail = $user->getEmail();

            $transactions = $this->entityManager->getRepository(Transaction::class)
                ->createQueryBuilder('t')
                ->select('c.name as course_name, c.type as course_type, t.createdAt, t.amount')
                ->leftJoin('t.course', 'c')
                ->where('t.user = :user')
                ->andWhere('t.createdAt BETWEEN :startDate AND :endDate')
                ->andWhere('t.type = :paymentType')
                ->setParameter('user', $user)
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate)
                ->setParameter('paymentType', Transaction::TYPE_PAYMENT)
                ->getQuery()
                ->getResult();

            if (empty($transactions)) {
                $io->writeln('У пользователя ' . $userEmail . ' нет оплаченных курсов за указанный период.');
                continue;
            }

            $totalAmount = 0;

            foreach ($transactions as &$transaction) {
                $transaction['course_type'] = $courseTypeNames[$transaction['course_type']] ?? 'Неизвестно';
                $totalAmount += $transaction['amount'];
            }

            $emailBody = $this->twig->render('emails/user_payment_report.html.twig', [
                'user' => $user,
                'transactions' => $transactions,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'totalAmount' => $totalAmount,
            ]);

            $email = (new Email())
                ->from('SkelD@studyon.com')
                ->to($userEmail)
                ->subject('Ваш отчет об оплаченных курсах за период ' . $startDate->format('d.m.Y') . ' - ' . $endDate->format('d.m.Y'))
                ->html($emailBody);

            try {
                $this->mailer->send($email);
                $io->writeln('Отчет успешно отправлен пользователю ' . $userEmail);
            } catch (\Exception $e) {
                $io->error('Ошибка при отправке отчета пользователю ' . $userEmail . ': ' . $e->getMessage());
            }
        }

        $io->success('Процесс отправки отчетов завершен.');

        return Command::SUCCESS;
    }
}