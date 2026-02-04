<?php

namespace App\Command;

use App\Entity\Servant;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:import-staff-users',
    description: 'Import Administradores y Asesores técnicos from Excel into Servant + User.',
)]
class ImportStaffUsersCommand extends Command
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
            ->addArgument('file', InputArgument::REQUIRED, 'Path to XLSX file (Servidores Públicos ÁCA)')
            ->addArgument('sheet', InputArgument::OPTIONAL, 'Sheet name', 'sPublicos')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write to DB')
            ->addOption('ignore-duplicate-keys', null, InputOption::VALUE_NONE, 'If duplicate Clave exists, keep first occurrence and skip the rest')
        ;
    }

    private function normalizeHeader(string $s): string
    {
        $s = str_replace("\u{00A0}", " ", $s);
        $s = trim($s);
        $s = mb_strtolower($s);

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($ascii !== false) {
            $s = $ascii;
        }

        $s = preg_replace('/\s+/', ' ', $s);
        $s = preg_replace('/[^a-z0-9 ]/', '', $s);

        return trim($s);
    }

    private function sanitizeEmail(?string $email): ?string
    {
        if ($email === null) return null;

        $email = trim(mb_strtolower($email));
        if ($email === '' || in_array($email, ['n/a', 'na', 's/c'], true)) return null;

        $email = str_replace([' ', "\n", "\r", "\t"], '', $email);
        $email = str_replace(',', '.', $email);

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $email);
        if ($ascii !== false) {
            $email = $ascii;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return null;

        return $email;
    }

    private function parseBirthMonthDay(mixed $value): array
    {
        if ($value === null) return [null, null];

        if ($value instanceof \DateTimeInterface) {
            return [(int) $value->format('n'), (int) $value->format('j')];
        }

        $s = trim((string) $value);
        if ($s === '') return [null, null];

        $s = mb_strtolower($s);
        $s = str_replace(['/', '.'], '-', $s);

        if (preg_match('/^(\d{1,2})\s*-\s*([a-záéíóúñ]+)/u', $s, $m)) {
            $day = (int) $m[1];
            $monTxt = $m[2];

            $monTxtAscii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $monTxt);
            if ($monTxtAscii !== false) $monTxt = $monTxtAscii;

            $monTxt = preg_replace('/[^a-z]/', '', $monTxt);

            $map = [
                'ene' => 1, 'enero' => 1,
                'feb' => 2, 'febrero' => 2,
                'mar' => 3, 'marzo' => 3,
                'abr' => 4, 'abril' => 4,
                'may' => 5, 'mayo' => 5,
                'jun' => 6, 'junio' => 6,
                'jul' => 7, 'julio' => 7,
                'ago' => 8, 'agosto' => 8,
                'sep' => 9, 'sept' => 9, 'septiembre' => 9,
                'oct' => 10, 'octubre' => 10,
                'nov' => 11, 'noviembre' => 11,
                'dic' => 12, 'diciembre' => 12,
            ];

            $month = $map[$monTxt] ?? null;
            return [$month, $day];
        }

        $ts = strtotime($s);
        if ($ts !== false) {
            $dt = (new \DateTimeImmutable())->setTimestamp($ts);
            return [(int) $dt->format('n'), (int) $dt->format('j')];
        }

        return [null, null];
    }

    private function randomPassword(int $len = 18): string
    {
        return rtrim(strtr(base64_encode(random_bytes($len)), '+/', '-_'), '=');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = (string) $input->getArgument('file');
        $sheetName = (string) $input->getArgument('sheet');
        $dryRun = (bool) $input->getOption('dry-run');
        $ignoreDupKeys = (bool) $input->getOption('ignore-duplicate-keys');

        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getSheetByName($sheetName);
        if (!$sheet) {
            $output->writeln("<error>Sheet not found: {$sheetName}</error>");
            return Command::FAILURE;
        }

        $rows = array_values($sheet->toArray(null, true, true, true));
        $header = $rows[0] ?? null;
        if (!is_array($header)) {
            $output->writeln("<error>Could not read header row</error>");
            return Command::FAILURE;
        }

        $col = [];
        foreach ($header as $letter => $label) {
            $n = $this->normalizeHeader((string) $label);
            if ($n !== '') $col[$n] = $letter;
        }

        // your file has "Applido Paterno" (typo) - keep it
        $required = [
            'clave del servidor publico',
            'rol',
            'nombre',
            'applido paterno',
            'apellido materno',
            'correo',
            'equipo',
            'fecha de cumpleanos',
        ];
        foreach ($required as $needed) {
            if (!isset($col[$needed])) {
                $output->writeln("<error>Missing column: {$needed}</error>");
                $output->writeln("<comment>Found: " . implode(', ', array_keys($col)) . "</comment>");
                return Command::FAILURE;
            }
        }

        // duplicate key detection
        $keyCounts = [];
        for ($i = 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            $k = trim((string) ($r[$col['clave del servidor publico']] ?? ''));
            if ($k === '') continue;
            $keyCounts[$k] = ($keyCounts[$k] ?? 0) + 1;
        }
        $dups = array_filter($keyCounts, fn ($c) => $c > 1);
        if (!$ignoreDupKeys && count($dups) > 0) {
            $output->writeln("<error>Duplicate 'Clave del Servidor Público' found. Fix the Excel or use --ignore-duplicate-keys</error>");
            foreach ($dups as $k => $c) {
                $output->writeln(" - {$k} appears {$c} times");
            }
            return Command::FAILURE;
        }

        $teamRepo = $this->em->getRepository(Team::class);
        $servantRepo = $this->em->getRepository(Servant::class);
        $userRepo = $this->em->getRepository(User::class);

        // caches to avoid duplicates before flush
        $teamCache = [];
        $servantByKey = [];
        $userByEmail = [];

        $createdUsers = 0;
        $updatedUsers = 0;
        $createdServants = 0;
        $updatedServants = 0;
        $skipped = 0;

        $seenKeys = [];

        for ($i = 1; $i < count($rows); $i++) {
            $r = $rows[$i];

            $keyRaw = trim((string) ($r[$col['clave del servidor publico']] ?? ''));
            if ($keyRaw === '') continue;

            if ($ignoreDupKeys && isset($seenKeys[$keyRaw])) {
                $skipped++;
                continue;
            }
            $seenKeys[$keyRaw] = true;

            $key = (int) $keyRaw;

            $roleRaw = trim((string) ($r[$col['rol']] ?? ''));
            $firstName = trim((string) ($r[$col['nombre']] ?? ''));
            $last1 = trim((string) ($r[$col['applido paterno']] ?? ''));
            $last2 = trim((string) ($r[$col['apellido materno']] ?? ''));
            $email = $this->sanitizeEmail((string) ($r[$col['correo']] ?? ''));

            $teamRaw = trim((string) ($r[$col['equipo']] ?? ''));
            $teamNumber = $teamRaw !== '' ? (int) $teamRaw : null;

            [$bm, $bd] = $this->parseBirthMonthDay($r[$col['fecha de cumpleanos']] ?? null);

            // Servant by key (cache -> DB)
            $servant = $servantByKey[$key] ?? null;
            if (!$servant) {
                $servant = $servantRepo->findOneBy(['key' => $key]);
            }

            $isNewServant = false;
            if (!$servant) {
                $servant = new Servant();
                $servant->setKey($key);
                $isNewServant = true;
            }

            $servant->setFirstName($firstName !== '' ? $firstName : 'N/D');
            $servant->setLastName1($last1 !== '' ? $last1 : 'N/D');
            $servant->setLastName2($last2 !== '' ? $last2 : null);
            $servant->setEmail($email);
            $servant->setBirthMonth($bm);
            $servant->setBirthDay($bd);

            $servantByKey[$key] = $servant;

            // Admin/advisor must have email to create login
            if ($email === null) {
                $output->writeln("<comment>Skipping user creation for key {$key}: invalid/missing email</comment>");
                $skipped++;
                if (!$dryRun) $this->em->persist($servant);
                continue;
            }

            $emailKey = mb_strtolower($email);

            // User by email (cache -> DB)
            $user = $userByEmail[$emailKey] ?? null;
            if (!$user) {
                $user = $userRepo->findOneBy(['email' => $emailKey]);
            }

            $isNewUser = false;
            if (!$user) {
                $user = new User();
                $user->setEmail($emailKey);
                $user->setPassword($this->hasher->hashPassword($user, $this->randomPassword()));
                $isNewUser = true;
            }

            $roles = match (mb_strtolower($roleRaw)) {
                'administrador' => ['ROLE_ADMIN'],
                'asesor tecnico', 'asesor técnico' => ['ROLE_ADVISOR'],
                default => ['ROLE_USER'],
            };
            $user->setRoles($roles);

            if (in_array('ROLE_ADVISOR', $roles, true) && $teamNumber !== null) {
                $team = $teamCache[$teamNumber] ?? null;
                if (!$team) {
                    $team = $teamRepo->findOneBy(['number' => $teamNumber]);
                    if ($team) $teamCache[$teamNumber] = $team;
                }
                $user->setTeam($team);
            } else {
                $user->setTeam(null);
            }

            $user->setServant($servant);

            $userByEmail[$emailKey] = $user;

            if (!$dryRun) {
                $this->em->persist($servant);
                $this->em->persist($user);
            }

            if ($isNewServant) $createdServants++;
            else $updatedServants++;

            if ($isNewUser) $createdUsers++;
            else $updatedUsers++;
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $output->writeln("<info>Done.</info>");
        $output->writeln("Servants: created={$createdServants}, updated={$updatedServants}");
        $output->writeln("Users: created={$createdUsers}, updated={$updatedUsers}");
        $output->writeln("Skipped={$skipped} (missing email or duplicate keys)");

        return Command::SUCCESS;
    }
}
