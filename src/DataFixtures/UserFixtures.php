<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        // Create Super Admin
        $superAdmin = new User();
        $superAdmin->setEmail('superadmin@landingpagecms.com');
        $superAdmin->setFirstName('Super');
        $superAdmin->setLastName('Admin');
        $superAdmin->setRoles(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_USER']);
        $superAdmin->setIsActive(true);
        $superAdmin->setPassword($this->passwordHasher->hashPassword($superAdmin, 'admin123'));
        $manager->persist($superAdmin);

        // Create Admin
        $admin = new User();
        $admin->setEmail('admin@landingpagecms.com');
        $admin->setFirstName('John');
        $admin->setLastName('Admin');
        $admin->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $admin->setIsActive(true);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        // Create regular users
        $users = [
            ['Marie', 'Dupont', 'marie.dupont@example.com'],
            ['Pierre', 'Martin', 'pierre.martin@example.com'],
            ['Sophie', 'Bernard', 'sophie.bernard@example.com'],
            ['Lucas', 'Dubois', 'lucas.dubois@example.com'],
            ['Emma', 'Thomas', 'emma.thomas@example.com'],
        ];

        foreach ($users as $userData) {
            $user = new User();
            $user->setFirstName($userData[0]);
            $user->setLastName($userData[1]);
            $user->setEmail($userData[2]);
            $user->setRoles(['ROLE_USER']);
            $user->setIsActive(true);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'user123'));
            
            // Set some users as inactive
            if (rand(0, 10) > 7) {
                $user->setIsActive(false);
            }
            
            $manager->persist($user);
        }

        $manager->flush();
    }
}