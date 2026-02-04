<?php

namespace App\Command;

use App\Entity\Servant;
use App\Entity\Unit;
use App\Entity\UnitAssignment;
use App\Entity\User;
use App\Entity\Enum\Assignment;
use App\Entity\Enum\Gender;
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
    name: 'app:import-directory',
    description: 'Import directorio (responsables/enlaces) from XLSM into Servant + UnitAssignment (optionally create portal users).',
)]
class ImportDirectoryAssignmentsCommand extends Command
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
            ->addArgument('file', InputArgument::REQUIRED, 'Path to XLSM file (Directorio_SIA_DB)')
            ->addArgument('sheet', InputArgument::OPTIONAL, 'Sheet name', 'directorio')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write to DB')
            ->addOption('include-non-generating', null, InputOption::VALUE_NONE, 'Also import assignments for units with isGenerating=false')
            ->addOption('create-users', null, InputOption::VALUE_NONE, 'Create portal User accounts when email is valid and unique')
        ;
    }

    private function normalize(string $s): string
    {
        $s = str_replace("\u{00A0}", " ", $s);
        $s = trim($s);
        $s = mb_strtolower($s);

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($ascii !== false) $s = $ascii;

        $s = preg_replace('/\s+/', ' ', $s);
        $s = preg_replace('/[^a-z0-9 ]/', '', $s);

        return trim($s);
    }

    private function isBlankish(?string $s): bool
    {
        if ($s === null) return true;

        $s = trim((string)$s);
        if ($s === '') return true;

        // Normalize common placeholders
        $u = mb_strtoupper($s);

        return in_array($u, [
            'N/A', 'N\\A', '#N/A',
            'N/D', 'N\\D',
            'NA', 'ND',
            '-', '--', 'SIN DATO', 'S/D',
        ], true);
    }

    private function cleanToken(string $s): ?string
    {
        $s = trim($s);
        return $this->isBlankish($s) ? null : $s;
    }


    private function sanitizeEmail(?string $email): ?string
    {
        if ($email === null) return null;
        $email = trim(mb_strtolower($email));
        if ($email === '' || in_array($email, ['n/a', 'na', 's/c'], true)) return null;

        $email = str_replace([' ', "\r", "\t"], '', $email);
        $email = str_replace(',', '.', $email);

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $email);
        if ($ascii !== false) $email = $ascii;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
        return $email;
    }

    private function splitMulti(?string $s): array
    {
        if ($s === null) return [];
        $s = trim((string)$s);
        if ($this->isBlankish($s)) return [];

        $parts = preg_split('/\s*;\s*/', $s) ?: [];
        $out = [];

        foreach ($parts as $p) {
            $p = $this->cleanToken($p);
            if ($p !== null) $out[] = $p;
        }

        return $out;
    }


    private function splitEmails(?string $s): array
    {
        if ($s === null) return [];
        $s = trim((string)$s);
        if ($this->isBlankish($s)) return [];

        $parts = preg_split('/[\r\n;]+/', $s) ?: [];
        $out = [];

        foreach ($parts as $p) {
            $email = $this->sanitizeEmail($p); // returns null if invalid/empty
            if ($email !== null) $out[] = $email;
        }

        return $out;
    }


    private function randomPassword(int $len = 18): string
    {
        return rtrim(strtr(base64_encode(random_bytes($len)), '+/', '-_'), '=');
    }

    private function findHeaderRowIndex(array $rows, array $mustContain): ?int
    {
        $limit = min(30, count($rows));
        for ($i = 0; $i < $limit; $i++) {
            $seen = [];
            foreach (($rows[$i] ?? []) as $cell) {
                $n = $this->normalize((string) $cell);
                if ($n !== '') $seen[$n] = true;
            }
            $ok = true;
            foreach ($mustContain as $need) {
                if (!isset($seen[$need])) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) return $i;
        }
        return null;
    }

    private function mapAssignment(string $raw): ?Assignment
    {
        $n = $this->normalize($raw);

        // 1) direct match against enum VALUES (your enum values are Spanish labels)
        foreach (Assignment::cases() as $case) {
            if ($n === $this->normalize($case->value)) {
                return $case;
            }
        }

        // 2) tolerance / synonyms
        if (str_contains($n, 'correspondencia')) return Assignment::MAIL;
        if ($n === 'tramite' || str_contains($n, 'tramite')) return Assignment::TRANSACT;
        if (str_contains($n, 'enlace')) return Assignment::LIAISON;
        if (str_contains($n, 'auxiliar')) return Assignment::ASSISTANT;

        return null;
    }

    private function mapGender(?string $raw): ?Gender
    {
        if ($raw === null) return null;

        $n = $this->normalize($raw);

        // common variants
        if (in_array($n, ['m', 'masculino', 'hombre'], true)) return Gender::Male;

        // your enum has Female='feminino' (typo), but users write 'femenino'
        if (in_array($n, ['f', 'femenino', 'feminino', 'mujer'], true)) return Gender::Female;

        if (in_array($n, ['otro', 'x'], true)) return Gender::Other;

        // fallback: direct match against enum values
        foreach (Gender::cases() as $case) {
            if ($n === $this->normalize($case->value)) {
                return $case;
            }
        }

        return null;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = (string) $input->getArgument('file');
        $sheetName = (string) $input->getArgument('sheet');
        $dryRun = (bool) $input->getOption('dry-run');
        $includeNonGenerating = (bool) $input->getOption('include-non-generating');
        $createUsers = (bool) $input->getOption('create-users');

        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getSheetByName($sheetName);
        if (!$sheet) {
            $output->writeln("<error>Sheet not found: {$sheetName}</error>");
            return Command::FAILURE;
        }

        $rows = array_values($sheet->toArray(null, true, true, true));

        // Your header starts at B2, so we search for it (don’t assume row 1).
        $mustContain = ['equipo', 'codigo', 'area operativa del sia', 'nombre', 'apellido paterno', 'correo'];
        $headerIdx = $this->findHeaderRowIndex($rows, $mustContain);
        if ($headerIdx === null) {
            $output->writeln("<error>Could not locate header row in sheet '{$sheetName}'.</error>");
            return Command::FAILURE;
        }

        $header = $rows[$headerIdx];
        $dataRows = array_slice($rows, $headerIdx + 1);

        $col = [];
        foreach ($header as $letter => $label) {
            $n = $this->normalize((string) $label);
            if ($n !== '') $col[$n] = $letter;
        }

        $required = ['codigo', 'area operativa del sia', 'nombre', 'apellido paterno', 'apellido materno', 'genero', 'correo'];
        foreach ($required as $need) {
            if (!isset($col[$need])) {
                $output->writeln("<error>Missing column: {$need}</error>");
                $output->writeln("<comment>Found: " . implode(', ', array_keys($col)) . "</comment>");
                return Command::FAILURE;
            }
        }

        $unitRepo = $this->em->getRepository(Unit::class);
        $servantRepo = $this->em->getRepository(Servant::class);
        $uaRepo = $this->em->getRepository(UnitAssignment::class);
        $userRepo = $this->em->getRepository(User::class);

        // In-memory caches (important because we don't flush after every row)
        // Prevents duplicates created during the same import run.
        $unitCache = [];
        $servantByEmail = [];
        $servantByName = [];
        $userByEmail = [];

        $createdServants = 0;
        $updatedServants = 0;
        $createdAssignments = 0;
        $skipped = 0;
        $createdUsers = 0;

        // Avoid duplicate assignments inside this run
        $seen = [];

        foreach ($dataRows as $r) {
            $unitCode = trim((string) ($r[$col['codigo']] ?? ''));

            // Skip Excel errors like #N/A, #VALUE!, etc.
            if ($unitCode === '' || str_starts_with($unitCode, '#')) {
                continue;
            }


            $unit = $unitCache[$unitCode] ?? null;
            if (!$unit) {
                $unit = $unitRepo->findOneBy(['code' => $unitCode]);
                if ($unit) {
                    $unitCache[$unitCode] = $unit;
                }
            }

            if (!$unit) {
                $output->writeln("<comment>Skip: Unit not found for code {$unitCode}</comment>");
                $skipped++;
                continue;
            }

            if (!$includeNonGenerating && !$unit->isGenerating()) {
                // no responsables/enlaces needed for non-generating units
                continue;
            }

            $assignmentRaw = trim((string) ($r[$col['area operativa del sia']] ?? ''));
            $assignment = $this->mapAssignment($assignmentRaw);
            if (!$assignment) {
                $output->writeln("<comment>Skip: Unknown assignment '{$assignmentRaw}' for unit {$unitCode}</comment>");
                $skipped++;
                continue;
            }

            // Liaison = applies to descendants by default
            $scope = ($assignment === Assignment::LIAISON) ? 'DESCENDANTS' : 'SELF';

            $firstNames = $this->splitMulti((string) ($r[$col['nombre']] ?? ''));
            $last1s = $this->splitMulti((string) ($r[$col['apellido paterno']] ?? ''));
            $last2s = $this->splitMulti((string) ($r[$col['apellido materno']] ?? ''));
            $emails = $this->splitEmails((string) ($r[$col['correo']] ?? ''));

            // If this row has no usable person info, skip completely
            if (count($firstNames) === 0 && count($last1s) === 0 && count($emails) === 0) {
                $skipped++;
                continue;
            }


            $genderRaw = (string) ($r[$col['genero']] ?? '');
            $gender = $this->mapGender($genderRaw);

            $n = max(count($firstNames), count($last1s), count($last2s), count($emails), 1);

            for ($i = 0; $i < $n; $i++) {
                $fn = $firstNames[$i] ?? ($firstNames[0] ?? null);
                $l1 = $last1s[$i] ?? ($last1s[0] ?? null);
                $l2 = $last2s[$i] ?? ($last2s[0] ?? null);

                $fn = $fn !== null ? trim($fn) : null;
                $l1 = $l1 !== null ? trim($l1) : null;
                $l2 = $l2 !== null ? trim($l2) : null;

                // If missing essential name parts, do NOT create a Servant
                if ($this->isBlankish($fn) || $this->isBlankish($l1)) {
                    $skipped++;
                    continue;
                }


                $email = null;
                if (count($emails) === $n) {
                    $email = $emails[$i] ?? null;
                } elseif (count($emails) === 1) {
                    $email = $emails[0] ?? null;
                }

                $fn = trim($fn);
                $l1 = trim($l1);
                $l2 = $l2 !== null ? trim($l2) : null;

                // Find existing servant (cache -> DB):
                $servant = null;

                $emailKey = $email !== null ? mb_strtolower($email) : null;
                $nameKey = $this->normalize($fn . ' ' . $l1 . ' ' . ($l2 ?? ''));

                if ($emailKey !== null && isset($servantByEmail[$emailKey])) {
                    $servant = $servantByEmail[$emailKey];
                }
                if (!$servant && $nameKey !== '' && isset($servantByName[$nameKey])) {
                    $servant = $servantByName[$nameKey];
                }

                if (!$servant && $emailKey !== null) {
                    $servant = $servantRepo->findOneBy(['email' => $emailKey]);
                }
                if (!$servant) {
                    $servant = $servantRepo->findOneBy([
                        'firstName' => $fn,
                        'lastName1' => $l1,
                        'lastName2' => $l2,
                    ]);
                }

                $isNewServant = false;
                if (!$servant) {
                    $servant = new Servant();
                    $servant->setFirstName($fn);
                    $servant->setLastName1($l1);
                    $servant->setLastName2($this->isBlankish($l2) ? null : $l2);
                    $isNewServant = true;
                }

                // Update servant fields
                if ($email !== null) {
                    $servant->setEmail($emailKey);
                }

                if ($gender !== null) {
                    // Your Servant::setGender() requires a non-null Gender
                    $servant->setGender($gender);
                }

                // Update caches (reuse same object within this run)
                if ($emailKey !== null) {
                    $servantByEmail[$emailKey] = $servant;
                }
                if ($nameKey !== '') {
                    $servantByName[$nameKey] = $servant;
                }

                if (!$dryRun) $this->em->persist($servant);

                if ($isNewServant) $createdServants++;
                else $updatedServants++;

                // Avoid duplicate assignments in the same run:
                $servantKey = $emailKey ?? ($fn . '|' . $l1 . '|' . ($l2 ?? ''));
                $dedupeKey = $unitCode . '|' . $servantKey . '|' . $assignment->name . '|' . $scope;
                if (isset($seen[$dedupeKey])) {
                    continue;
                }
                $seen[$dedupeKey] = true;

                if (!$dryRun) {
                    $existing = $uaRepo->findOneBy([
                        'unit' => $unit,
                        'servant' => $servant,
                        'assignment' => $assignment,
                    ]);

                    if ($existing) {
                        $existing->setScope($scope);
                    } else {
                        $ua = new UnitAssignment();
                        $ua->setUnit($unit);
                        $ua->setServant($servant);
                        $ua->setAssignment($assignment);
                        $ua->setScope($scope);
                        $this->em->persist($ua);
                        $createdAssignments++;
                    }
                }

                // Optional: create portal login users
                if ($createUsers && $emailKey !== null) {

                    // 1) If this servant already has a user, DO NOT create a new one (1-1 constraint)
                    $existingUserForServant = null;
                    if ($servant->getId() !== null) {
                        $existingUserForServant = $userRepo->findOneBy(['servant' => $servant]);
                    }

                    if ($existingUserForServant) {
                        // Optional: ensure it has ROLE_PORTAL (without removing existing roles)
                        $roles = $existingUserForServant->getRoles();
                        if (!in_array('ROLE_PORTAL', $roles, true)) {
                            $roles[] = 'ROLE_PORTAL';
                            $existingUserForServant->setRoles(array_values(array_unique($roles)));
                        }
                        continue; // critical: avoid creating a second user for same servant
                    }

                    // 2) Otherwise, use email to find/create user
                    $user = $userRepo->findOneBy(['email' => $emailKey]);

                    if ($user) {
                        // email exists already; if user has no servant, link it
                        if ($user->getServant() === null) {
                            $user->setServant($servant);
                        } else {
                            // email belongs to another servant -> conflict, skip (do NOT relink)
                            if ($user->getServant()->getId() !== $servant->getId()) {
                                // you can log this if you want
                                // $output->writeln("<comment>Email {$emailKey} already belongs to a different Servant. Skipping.</comment>");
                                continue;
                            }
                        }

                        // optional: add role
                        $roles = $user->getRoles();
                        if (!in_array('ROLE_PORTAL', $roles, true)) {
                            $roles[] = 'ROLE_PORTAL';
                            $user->setRoles(array_values(array_unique($roles)));
                        }

                        continue;
                    }

                    // 3) Create a new user if email is not in use and servant has no user
                    $user = new User();
                    $user->setEmail($emailKey);
                    $user->setRoles(['ROLE_PORTAL']);
                    $user->setPassword($this->hasher->hashPassword($user, $this->randomPassword()));
                    $user->setServant($servant);
                    $user->setTeam(null);

                    if (!$dryRun) {
                        $this->em->persist($user);
                    }
                    $createdUsers++;
                }

            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $output->writeln("<info>Done.</info>");
        $output->writeln("Servants: created={$createdServants}, updated={$updatedServants}");
        $output->writeln("Assignments created={$createdAssignments}");
        $output->writeln("Users created={$createdUsers}" . ($createUsers ? "" : " (use --create-users to enable)"));
        $output->writeln("Skipped={$skipped}");

        return Command::SUCCESS;
    }
}
