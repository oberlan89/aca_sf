<?php

namespace App\Command;

use App\Entity\Servant;
use App\Entity\UnitAssignment;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-portal-users',
    description: 'Create (or upgrade) portal users for Servants that appear in UnitAssignment and have an email.',
)]
class CreatePortalUsersCommand extends Command
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
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write to DB')
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'Role to add', 'ROLE_PORTAL')
        ;
    }

    private function randomPassword(int $len = 18): string
    {
        return rtrim(strtr(base64_encode(random_bytes($len)), '+/', '-_'), '=');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $role = (string) $input->getOption('role');

        $userRepo = $this->em->getRepository(User::class);

        // All servants who appear in assignments AND have email
        $qb = $this->em->createQueryBuilder();
        $qb->select('DISTINCT s')
            ->from(Servant::class, 's')
            ->innerJoin(UnitAssignment::class, 'ua', 'WITH', 'ua.servant = s')
            ->where('s.email IS NOT NULL')
            ->andWhere('s.email <> \'\'')
        ;

        /** @var Servant[] $servants */
        $servants = $qb->getQuery()->getResult();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        // Avoid duplicate email collisions during the run
        $seenEmail = [];

        foreach ($servants as $servant) {
            $email = $servant->getEmail();
            if (!$email) { $skipped++; continue; }

            $email = mb_strtolower(trim($email));
            if ($email === '') { $skipped++; continue; }

            if (isset($seenEmail[$email])) {
                // same email attached to multiple servants -> skip (data issue)
                $skipped++;
                continue;
            }
            $seenEmail[$email] = true;

            // If a user already exists for this servant (1-1), just add role
            $user = $userRepo->findOneBy(['servant' => $servant]);

            // Otherwise, reuse user by email if exists
            if (!$user) {
                $user = $userRepo->findOneBy(['email' => $email]);
                if ($user && $user->getServant() !== null && $user->getServant()->getId() !== $servant->getId()) {
                    // email belongs to someone else -> skip
                    $skipped++;
                    continue;
                }
            }

            if ($user) {
                // Link if missing
                if ($user->getServant() === null) {
                    $user->setServant($servant);
                }

                $roles = $user->getRoles();
                if (!in_array($role, $roles, true)) {
                    $roles[] = $role;
                    $user->setRoles(array_values(array_unique($roles)));
                }

                $updated++;
                if (!$dryRun) $this->em->persist($user);
                continue;
            }

            // Create new user
            $user = new User();
            $user->setEmail($email);
            $user->setRoles([$role]);
            $user->setPassword($this->hasher->hashPassword($user, $this->randomPassword()));
            $user->setServant($servant);
            $user->setTeam(null);

            $created++;
            if (!$dryRun) $this->em->persist($user);
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $output->writeln("<info>Done.</info>");
        $output->writeln("Created={$created}, Updated={$updated}, Skipped={$skipped}");

        return Command::SUCCESS;
    }
}
