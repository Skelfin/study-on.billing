<?php

namespace App\DataFixtures;

use App\Entity\BillingUser;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class BillingUserFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Создание пользователя с ролью ROLE_USER
        $user = new BillingUser();
        $user->setEmail('user@example.com');
        $user->setRoles(['ROLE_USER']);
        $user->setBalance(1000.0); // Устанавливаем баланс
        $hashedPassword = $this->passwordHasher->hashPassword($user, 'password123');
        $user->setPassword($hashedPassword);
        $manager->persist($user);

        // Создание пользователя с ролью ROLE_SUPER_ADMIN
        $admin = new BillingUser();
        $admin->setEmail('admin@example.com');
        $admin->setRoles(['ROLE_SUPER_ADMIN']);
        $admin->setBalance(5000.0); // Устанавливаем баланс
        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'adminpassword');
        $admin->setPassword($hashedPassword);
        $manager->persist($admin);

        // Сохранение данных в базе данных
        $manager->flush();
    }
}