<?php

namespace App\Command;

use App\Entity\Admin;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée un administrateur pour le back-office',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse email')
            ->addArgument('password', InputArgument::REQUIRED, 'Mot de passe');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email');
        $password = $input->getArgument('password');

        $existing = $this->em->getRepository(Admin::class)->findOneBy(['email' => $email]);
        if ($existing) {
            $io->error("Un administrateur avec l'email {$email} existe déjà.");
            return Command::FAILURE;
        }

        $admin = new Admin();
        $admin->setEmail($email);
        $admin->setPassword($this->hasher->hashPassword($admin, $password));

        $this->em->persist($admin);
        $this->em->flush();

        $io->success("Administrateur {$email} créé avec succès.");

        return Command::SUCCESS;
    }
}
