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
        $user = new BillingUser();
        $user->setEmail('user@example.com');
        $user->setRoles(['ROLE_USER']);
        $user->setBalance(1000.0);
        $hashedPassword = $this->passwordHasher->hashPassword($user, '1234567');
        $user->setPassword($hashedPassword);
        $manager->persist($user);

        $admin = new BillingUser();
        $admin->setEmail('admin@example.com');
        $admin->setRoles(['ROLE_SUPER_ADMIN']);
        $admin->setBalance(5000.10);
        $hashedPassword = $this->passwordHasher->hashPassword($admin, '1234567');
        $admin->setPassword($hashedPassword);
        $manager->persist($admin);

        $manager->flush();
    }
}