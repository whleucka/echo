<?php

namespace Echo\Framework\Console\Commands;

use App\Services\GeoIpService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'activity:geocode', description: 'Backfill country codes for existing activity records')]
class ActivityGeocodeCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('batch', 'b', InputOption::VALUE_OPTIONAL, 'Batch size for processing', 1000);
        $this->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Maximum records to process (0 = all)', 0);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $batchSize = (int) $input->getOption('batch');
        $limit = (int) $input->getOption('limit');

        $geoIp = new GeoIpService();

        if (!$geoIp->isAvailable()) {
            $output->writeln("<error>GeoIP database not found.</error>");
            $output->writeln("Run <comment>php echo geoip:update</comment> to download it first.");
            return Command::FAILURE;
        }

        $output->writeln("Backfilling country codes for activity records...");

        try {
            // Count records needing geocoding
            $totalNull = db()->fetch(
                "SELECT COUNT(*) as cnt FROM activity WHERE country_code IS NULL AND ip IS NOT NULL"
            );
            $pending = (int) ($totalNull['cnt'] ?? 0);

            if ($pending === 0) {
                $output->writeln("<info>All records already have country codes.</info>");
                return Command::SUCCESS;
            }

            $toProcess = $limit > 0 ? min($limit, $pending) : $pending;
            $output->writeln(sprintf("Found %s records to geocode (processing %s).",
                number_format($pending),
                number_format($toProcess)
            ));

            $processed = 0;
            $resolved = 0;
            $lastId = 0;

            while ($processed < $toProcess) {
                $currentBatch = min($batchSize, $toProcess - $processed);

                $rows = db()->fetchAll(
                    "SELECT id, ip FROM activity WHERE country_code IS NULL AND ip IS NOT NULL AND id > ? ORDER BY id ASC LIMIT ?",
                    [$lastId, $currentBatch]
                );

                if (empty($rows)) {
                    break;
                }

                foreach ($rows as $row) {
                    $lastId = (int) $row['id'];
                    $ipStr = long2ip((int) $row['ip']);
                    $countryCode = $geoIp->getCountryCode($ipStr);

                    if ($countryCode !== null) {
                        db()->execute(
                            "UPDATE activity SET country_code = ? WHERE id = ?",
                            [$countryCode, $row['id']]
                        );
                        $resolved++;
                    }

                    $processed++;
                }

                $output->write(sprintf(
                    "\r  Processed: %s / %s  (resolved: %s)",
                    number_format($processed),
                    number_format($toProcess),
                    number_format($resolved)
                ));
            }

            $output->writeln(""); // newline after progress
            $output->writeln(sprintf(
                "<info>Done. Processed %s records, resolved %s country codes.</info>",
                number_format($processed),
                number_format($resolved)
            ));

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln("<error>Error: " . $e->getMessage() . "</error>");
            return Command::FAILURE;
        }
    }
}
