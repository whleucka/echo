<?php

namespace App\Services;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;

class GeoIpService
{
    private ?Reader $reader = null;
    private string $dbPath;

    public function __construct()
    {
        $root = config('paths.root');
        $this->dbPath = $root . 'storage/geoip/GeoLite2-Country.mmdb';
    }

    /**
     * Get the ISO 3166-1 alpha-2 country code for an IP address
     */
    public function getCountryCode(string $ip): ?string
    {
        if (!$this->isAvailable()) {
            return null;
        }

        // Skip private/reserved IPs
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        try {
            $reader = $this->getReader();
            $record = $reader->country($ip);
            return $record->country->isoCode;
        } catch (AddressNotFoundException) {
            return null;
        } catch (\Exception $e) {
            error_log("GeoIP lookup failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if the GeoIP database is available
     */
    public function isAvailable(): bool
    {
        return file_exists($this->dbPath);
    }

    /**
     * Get the database file path
     */
    public function getDatabasePath(): string
    {
        return $this->dbPath;
    }

    /**
     * Get the database directory path
     */
    public function getDatabaseDir(): string
    {
        return dirname($this->dbPath);
    }

    private function getReader(): Reader
    {
        if ($this->reader === null) {
            $this->reader = new Reader($this->dbPath);
        }
        return $this->reader;
    }

    public function __destruct()
    {
        if ($this->reader !== null) {
            $this->reader->close();
        }
    }
}
