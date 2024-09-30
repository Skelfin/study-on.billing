<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;
use Symfony\Component\Mime\Email;
use App\Entity\Transaction;

#[AsCommand(
    name: 'payment:ending:notification',
    description: 'Отправка уведомлений пользователям о скором окончании аренды курсов',
)]
class PaymentEndingNotificationCommand extends Command
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
        $io->writeln('Начало процесса отправки уведомлений...');

        $tomorrow = new \DateTime('tomorrow');
        $tomorrow->setTime(0, 0, 0);

        $dayAfterTomorrow = clone $tomorrow;
        $dayAfterTomorrow->modify('+1 day');

        $transactionRepository = $this->entityManager->getRepository(Transaction::class);

        $expiringTransactions = $transactionRepository->createQueryBuilder('t')
            ->where('t.expiresAt >= :tomorrow')
            ->andWhere('t.expiresAt < :dayAfterTomorrow')->andWhere('t.type = :paymentType')
            ->setParameter('tomorrow', $tomorrow)
            ->setParameter('dayAfterTomorrow', $dayAfterTomorrow)
            ->setParameter('paymentType', Transaction::TYPE_PAYMENT)
            ->getQuery()
            ->getResult();

        $usersCourses = [];

        foreach ($expiringTransactions as $transaction) {
            /** @var Transaction $transaction */
            $user = $transaction->getUser();
            $course = $transaction->getCourse();
            $expiresAt = $transaction->getExpiresAt();

            if (!$user || !$course) {
                continue;
            }

            $userId = $user->getId();
            if (!isset($usersCourses[$userId])) {
                $usersCourses[$userId] = [
                    'user' => $user,
                    'courses' => [],
                ];
            }

            $usersCourses[$userId]['courses'][] = [
                'name' => $course->getName(),
                'expiresAt' => $expiresAt,
            ];
        }

        foreach ($usersCourses as $userData) {
            /** @var BillingUser $user */
            $user = $userData['user'];
            $courses = $userData['courses'];

            $emailBody = $this->twig->render('emails/course_expiring_notification.html.twig', [
                'courses' => $courses,
            ]);

            $email = (new Email())
                ->from('SkelD@studyon.com')
                ->to($user->getEmail())
                ->subject('Уведомление об окончании аренды курсов')
                ->html($emailBody);

            try {
                $this->mailer->send($email);
                $io->writeln('Письмо отправлено пользователю: ' . $user->getEmail());
            } catch (\Exception $e) {
                $io->error('Ошибка при отправке письма пользователю: ' . $user->getEmail());
                $io->error($e->getMessage());
            }
        }

        $io->success('Процесс отправки уведомлений завершен.');

        return Command::SUCCESS;
    }
}