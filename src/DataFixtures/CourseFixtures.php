<?php

namespace App\DataFixtures;

use App\Entity\Course;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CourseFixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $course1 = new Course();
        $course1->setCode('1course');
        $course1->setName('Symfony Basics');
        $course1->setType(Course::TYPE_RENT);
        $course1->setPrice(99.90);
        $manager->persist($course1);

        $course2 = new Course();
        $course2->setCode('2course');
        $course2->setName('Php Advanced');
        $course2->setType(Course::TYPE_FREE);
        $course2->setPrice(0.00);
        $manager->persist($course2);

        $course3 = new Course();
        $course3->setCode('3course');
        $course3->setName('Doctrine Essentials');
        $course3->setType(Course::TYPE_BUY);
        $course3->setPrice(159.00);
        $manager->persist($course3);

        $manager->flush();
    }
}