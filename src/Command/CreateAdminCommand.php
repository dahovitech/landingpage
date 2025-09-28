<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Créer un utilisateur administrateur',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email de l\'administrateur')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Mot de passe de l\'administrateur')
            ->addOption('first-name', null, InputOption::VALUE_REQUIRED, 'Prénom de l\'administrateur')
            ->addOption('last-name', null, InputOption::VALUE_REQUIRED, 'Nom de l\'administrateur')
            ->addOption('super-admin', null, InputOption::VALUE_NONE, 'Créer un super administrateur')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Get user input
        $email = $input->getOption('email') ?: $io->ask('Email de l\'administrateur');
        $password = $input->getOption('password') ?: $io->askHidden('Mot de passe de l\'administrateur');
        $firstName = $input->getOption('first-name') ?: $io->ask('Prénom de l\'administrateur');
        $lastName = $input->getOption('last-name') ?: $io->ask('Nom de l\'administrateur');
        $isSuperAdmin = $input->getOption('super-admin');

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Email invalide');
            return Command::FAILURE;
        }

        // Check if user already exists
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $io->error('Un utilisateur avec cet email existe déjà');
            return Command::FAILURE;
        }

        // Create user
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setIsActive(true);

        // Set roles
        if ($isSuperAdmin) {
            $user->setRoles(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_USER']);
        } else {
            $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        }

        // Hash password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Save to database
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf(
            '%s créé avec succès : %s (%s %s)',
            $isSuperAdmin ? 'Super administrateur' : 'Administrateur',
            $email,
            $firstName,
            $lastName
        ));

        return Command::SUCCESS;
    }
}