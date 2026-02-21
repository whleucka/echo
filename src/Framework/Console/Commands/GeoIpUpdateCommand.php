<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'geoip:update', description: 'Download/update the MaxMind GeoLite2-Country database')]
class GeoIpUpdateCommand extends Command
{
    private const DOWNLOAD_URL = 'https://download.maxmind.com/geoip/databases/GeoLite2-Country/download?suffix=tar.gz';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $accountId = env('MAXMIND_ACCOUNT_ID');
        $licenseKey = env('MAXMIND_LICENSE_KEY');

        if (empty($accountId) || empty($licenseKey)) {
            $output->writeln("<error>MAXMIND_ACCOUNT_ID and/or MAXMIND_LICENSE_KEY is not set in your .env file.</error>");
            $output->writeln("");
            $output->writeln("To get a free account and license key:");
            $output->writeln("  1. Create an account at https://www.maxmind.com/en/geolite2/signup");
            $output->writeln("  2. Your Account ID is shown on your account page");
            $output->writeln("  3. Generate a license key at https://www.maxmind.com/en/accounts/current/license-key");
            $output->writeln("  4. Add both to your .env file:");
            $output->writeln("     MAXMIND_ACCOUNT_ID=your_account_id");
            $output->writeln("     MAXMIND_LICENSE_KEY=your_license_key");
            return Command::FAILURE;
        }

        $root = config('paths.root');
        $geoipDir = $root . 'storage/geoip';
        $dbPath = $geoipDir . '/GeoLite2-Country.mmdb';

        // Ensure the directory exists
        if (!is_dir($geoipDir)) {
            if (!mkdir($geoipDir, 0755, true)) {
                $output->writeln("<error>Failed to create directory: $geoipDir</error>");
                return Command::FAILURE;
            }
        }

        $tmpFile = $geoipDir . '/GeoLite2-Country.tar.gz';

        $output->writeln("Downloading GeoLite2-Country database...");

        try {
            // Download the archive using Basic Auth
            $ch = curl_init(self::DOWNLOAD_URL);
            $fp = fopen($tmpFile, 'wb');

            if (!$fp) {
                $output->writeln("<error>Failed to open temp file for writing.</error>");
                return Command::FAILURE;
            }

            curl_setopt_array($ch, [
                CURLOPT_FILE => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 300,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => $accountId . ':' . $licenseKey,
                CURLOPT_USERAGENT => 'echo-geoip-updater/1.0',
            ]);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            fclose($fp);

            if (!$result || $httpCode < 200 || $httpCode >= 300) {
                @unlink($tmpFile);
                if ($error) {
                    $output->writeln(sprintf("<error>Download failed: %s</error>", $error));
                } else {
                    $output->writeln(sprintf("<error>Download failed (HTTP %d)</error>", $httpCode));
                }
                if ($httpCode === 401) {
                    $output->writeln("<comment>Check your MAXMIND_ACCOUNT_ID and MAXMIND_LICENSE_KEY.</comment>");
                }
                return Command::FAILURE;
            }

            // Verify we got a real file, not an error page
            $fileSize = filesize($tmpFile);
            if ($fileSize < 1024) {
                $content = file_get_contents($tmpFile);
                @unlink($tmpFile);
                $output->writeln("<error>Download returned an unexpected response:</error>");
                $output->writeln($content);
                return Command::FAILURE;
            }

            $output->writeln("Extracting database...");

            // Extract .mmdb from the tar.gz
            $phar = new \PharData($tmpFile);
            $phar->decompress(); // creates .tar

            $tarFile = $geoipDir . '/GeoLite2-Country.tar';
            $tar = new \PharData($tarFile);

            $extracted = false;
            $iterator = new \RecursiveIteratorIterator($tar);
            foreach ($iterator as $entry) {
                /** @var \PharFileInfo $entry */
                if (str_ends_with($entry->getFilename(), '.mmdb')) {
                    file_put_contents($dbPath, file_get_contents($entry->getPathname()));
                    $extracted = true;
                    break;
                }
            }

            // Cleanup temp files
            @unlink($tmpFile);
            @unlink($tarFile);

            if (!$extracted) {
                $output->writeln("<error>Could not find .mmdb file in the archive.</error>");
                return Command::FAILURE;
            }

            $size = number_format(filesize($dbPath) / 1024 / 1024, 1);
            $output->writeln(sprintf("<info>GeoLite2-Country database updated successfully (%s MB).</info>", $size));
            return Command::SUCCESS;

        } catch (\Exception $e) {
            @unlink($tmpFile);
            $output->writeln("<error>Error: " . $e->getMessage() . "</error>");
            return Command::FAILURE;
        }
    }
}
