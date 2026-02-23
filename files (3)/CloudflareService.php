<?php

namespace Paymenter\Extensions\Others\DomainProvisioner\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudflareService
{
    private const API_BASE = 'https://api.cloudflare.com/client/v4';

    public function __construct(
        private string $apiToken,
        private string $zoneId,
        private bool   $proxied = false,
    ) {}

    /**
     * Erstellt einen A-Record. Falls er bereits existiert, wird er aktualisiert.
     * Gibt die Record-ID zurück.
     */
    public function createARecord(string $name, string $ip, int $ttl = 1): string
    {
        $response = Http::withToken($this->apiToken)
            ->post(self::API_BASE . "/zones/{$this->zoneId}/dns_records", [
                'type'    => 'A',
                'name'    => $name,
                'content' => $ip,
                'ttl'     => $ttl,
                'proxied' => $this->proxied,
            ]);

        $result = $response->json();

        // Fehlercode 81057 = Record existiert bereits → Update
        if (!($result['success'] ?? false)) {
            $errorCodes = collect($result['errors'] ?? [])->pluck('code');
            if ($errorCodes->contains(81057)) {
                Log::info("[DomainProvisioner/Cloudflare] A-Record {$name} existiert bereits – Update.");
                return $this->updateARecord($name, $ip, $ttl);
            }
            throw new \RuntimeException('Cloudflare Fehler: ' . json_encode($result['errors'] ?? []));
        }

        return $result['result']['id'];
    }

    /**
     * Sucht einen bestehenden A-Record und aktualisiert ihn.
     */
    public function updateARecord(string $name, string $ip, int $ttl = 1): string
    {
        $recordId = $this->findRecord($name);
        if (!$recordId) {
            throw new \RuntimeException("Cloudflare: A-Record {$name} nicht für Update gefunden.");
        }

        $response = Http::withToken($this->apiToken)
            ->put(self::API_BASE . "/zones/{$this->zoneId}/dns_records/{$recordId}", [
                'type'    => 'A',
                'name'    => $name,
                'content' => $ip,
                'ttl'     => $ttl,
                'proxied' => $this->proxied,
            ]);

        $result = $response->json();
        if (!($result['success'] ?? false)) {
            throw new \RuntimeException('Cloudflare Update-Fehler: ' . json_encode($result['errors'] ?? []));
        }

        return $recordId;
    }

    /**
     * Löscht einen DNS-Record anhand seiner ID.
     */
    public function deleteRecord(string $recordId): void
    {
        $response = Http::withToken($this->apiToken)
            ->delete(self::API_BASE . "/zones/{$this->zoneId}/dns_records/{$recordId}");

        $result = $response->json();
        if (!($result['success'] ?? false)) {
            throw new \RuntimeException('Cloudflare Löschen-Fehler: ' . json_encode($result['errors'] ?? []));
        }
    }

    /**
     * Sucht einen A-Record anhand des Namens und gibt seine ID zurück.
     */
    public function findRecord(string $name): ?string
    {
        $response = Http::withToken($this->apiToken)
            ->get(self::API_BASE . "/zones/{$this->zoneId}/dns_records", [
                'type' => 'A',
                'name' => $name,
            ]);

        $records = $response->json('result', []);
        return $records[0]['id'] ?? null;
    }
}
