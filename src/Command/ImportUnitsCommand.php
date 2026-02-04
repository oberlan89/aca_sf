<?php

namespace App\Command;

use App\Entity\Subfondo;
use App\Entity\Team;
use App\Entity\Unit;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-units',
    description: 'Import Unit tree from Excel (Manual_2025).',
)]
class ImportUnitsCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to XLSX file')
            ->addArgument('sheet', InputArgument::OPTIONAL, 'Sheet name', 'Manual_2025')
        ;
    }

    private function parseBool(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        // PhpSpreadsheet might give booleans, ints, floats, or strings
        if (is_bool($value)) {
            return $value;
        }

        $v = trim(mb_strtolower((string) $value));

        if ($v === '') {
            return false;
        }

        // truthy
        if (in_array($v, ['1', 'true', 't', 'yes', 'y', 'si', 'sí', 's'], true)) {
            return true;
        }

        // falsy
        if (in_array($v, ['0', 'false', 'f', 'no', 'n'], true)) {
            return false;
        }

        // Default (safe): false
        return false;
    }

    private function normalizeHeader(string $s): string
    {
        // Remove non-breaking spaces and trim
        $s = str_replace("\u{00A0}", " ", $s);
        $s = trim($s);

        // Lowercase
        $s = mb_strtolower($s);

        // Remove accents (iconv is available on your system)
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($ascii !== false) {
            $s = $ascii;
        }

        // Collapse whitespace
        $s = preg_replace('/\s+/', ' ', $s);

        // Remove punctuation that Excel users sometimes add
        $s = preg_replace('/[^a-z0-9 ]/', '', $s);

        return trim($s);
    }

    private function findHeaderRowIndex(array $rows, array $mustContain): ?int
    {
        // Scan first 20 rows looking for a header-like row
        $limit = min(20, count($rows));
        for ($i = 0; $i < $limit; $i++) {
            $row = $rows[$i];
            $seen = [];
            foreach ($row as $cell) {
                $n = $this->normalizeHeader((string)$cell);
                if ($n !== '') {
                    $seen[$n] = true;
                }
            }

            $ok = true;
            foreach ($mustContain as $needed) {
                if (!isset($seen[$needed])) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                return $i;
            }
        }
        return null;
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = (string) $input->getArgument('file');
        $sheetName = (string) $input->getArgument('sheet');

        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getSheetByName($sheetName);
        if (!$sheet) {
            $output->writeln("<error>Sheet not found: {$sheetName}</error>");
            return Command::FAILURE;
        }

        $rows = $sheet->toArray(null, true, true, true);
        $rows = array_values($rows);


// Find header row
        $mustContain = ['codigo', 'unidad', 'equipo', 'codigo padre inmediato'];
        $headerRowIndex = $this->findHeaderRowIndex($rows, $mustContain);

        if ($headerRowIndex === null) {
            $output->writeln('<error>Could not find the header row. Make sure the sheet contains: Código, Unidad, Equipo, Código Padre Inmediato.</error>');
            return Command::FAILURE;
        }

        $header = $rows[$headerRowIndex];
        $dataRows = array_slice($rows, $headerRowIndex + 1);

// Build map normalized header => column letter
        $col = [];
        foreach ($header as $letter => $label) {
            $n = $this->normalizeHeader((string) $label);
            if ($n !== '') {
                $col[$n] = $letter;
            }
        }

// Required headers (normalized)
        $required = ['codigo', 'unidad', 'equipo', 'codigo padre inmediato', 'subfondo', 'codificacion'];

// Find “esta generando” column (normalized)
        $genKey = null;
        foreach (['esta generando', 'estagenerando', 'generando'] as $candidate) {
            if (isset($col[$candidate])) {
                $genKey = $candidate;
                break;
            }
        }

        foreach ($required as $needed) {
            if (!isset($col[$needed])) {
                $output->writeln("<error>Missing column: {$needed}</error>");
                $output->writeln("<comment>Found headers: ".implode(', ', array_keys($col))."</comment>");
                return Command::FAILURE;
            }
        }
        if ($genKey === null) {
            $output->writeln("<error>Missing column: esta generando (normalized). Found headers: ".implode(', ', array_keys($col))."</error>");
            return Command::FAILURE;
        }

// Repos
        $unitRepo = $this->em->getRepository(Unit::class);
        $teamRepo = $this->em->getRepository(Team::class);
        $subRepo  = $this->em->getRepository(Subfondo::class);

// Caches
        $teams = [];
        $subfondos = [];
        $unitsByCode = [];

// PASS 0: upsert Teams and Subfondos
        foreach ($dataRows as $r) {
            $teamRaw = trim((string)($r[$col['equipo']] ?? ''));
            if ($teamRaw !== '') {
                $teamNumber = (int) $teamRaw;
                if (!isset($teams[$teamNumber])) {
                    $team = $teamRepo->findOneBy(['number' => $teamNumber]) ?? new Team();
                    $team->setNumber($teamNumber);
                    $this->em->persist($team);
                    $teams[$teamNumber] = $team;
                }
            }

            $subCode = trim((string)($r[$col['codificacion']] ?? ''));
            $subName = trim((string)($r[$col['subfondo']] ?? ''));
            if ($subCode !== '') {
                if (!isset($subfondos[$subCode])) {
                    $sub = $subRepo->findOneBy(['code' => $subCode]) ?? new Subfondo();
                    $sub->setCode($subCode);
                    $sub->setName($subName);
                    $this->em->persist($sub);
                    $subfondos[$subCode] = $sub;
                } else {
                    $subfondos[$subCode]->setName($subName);
                }
            }
        }
        $this->em->flush();

// PASS 1: upsert Units without parent
        foreach ($dataRows as $r) {
            $code = trim((string)($r[$col['codigo']] ?? ''));
            if ($code === '') {
                continue;
            }

            $unit = $unitRepo->findOneBy(['code' => $code]) ?? new Unit();
            $unit->setCode($code);
            $unit->setName(trim((string)($r[$col['unidad']] ?? '')));

            // Subfondo required
            $subCode = trim((string)($r[$col['codificacion']] ?? ''));
            if ($subCode === '' || !isset($subfondos[$subCode])) {
                $output->writeln("<error>Missing Subfondo for unit {$code}</error>");
                return Command::FAILURE;
            }
            $unit->setSubfondo($subfondos[$subCode]);

            // Team: may be empty for root
            $teamRaw = trim((string)($r[$col['equipo']] ?? ''));
            if ($teamRaw === '') {
                $unit->setTeam(null); // only ok if Unit.team is nullable
            } else {
                $teamNumber = (int) $teamRaw;
                $unit->setTeam($teams[$teamNumber]);
            }

            // Está generando
            $genValue = $r[$col[$genKey]] ?? null;
            $unit->setIsGenerating($this->parseBool($genValue));

            $this->em->persist($unit);
            $unitsByCode[$code] = $unit;
        }
        $this->em->flush();

// PASS 2: set parents
        foreach ($dataRows as $r) {
            $code = trim((string)($r[$col['codigo']] ?? ''));
            if ($code === '') {
                continue;
            }

            $parentCode = trim((string)($r[$col['codigo padre inmediato']] ?? ''));
            if ($parentCode === '') {
                continue;
            }

            $unit = $unitsByCode[$code] ?? $unitRepo->findOneBy(['code' => $code]);
            $parent = $unitsByCode[$parentCode] ?? $unitRepo->findOneBy(['code' => $parentCode]);

            if ($unit && $parent) {
                $unit->setParent($parent);
            }
        }
        $this->em->flush();

        $output->writeln('<info>Import finished successfully.</info>');
        return Command::SUCCESS;

    }
}
