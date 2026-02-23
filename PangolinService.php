<?php

namespace Paymenter\Extensions\Others\DomainProvisioner\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PangolinService
{
    public function __construct(
        private string  $baseUrl,
        private string  $apiKey,
        private string  $orgId,
        private ?string $siteId = null,
    ) {
        // Abschließendes / entfernen
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Erstellt eine Pangolin Resource (Domain-Eintrag) + Target (Ziel-Server).
     * Gibt die Resource-ID zurück.
     */
    public function createResource(string $domain, string $targetIp, int $targetPort = 80): string
    {
        // Subdomain-Teil extrahieren (nur der erste Teil vor dem ersten Punkt)
        $subdomain = explode('.', $domain)[0];

        $resourceData = [
            'name'        => $domain,
            'subdomain'   => $subdomain,
            'http'        => true,
            'ssl'         => true,
            'blockAccess' => false,
        ];

        if ($this->siteId) {
            $resourceData['siteId'] = $this->siteId;
        }

        // 1. Resource anlegen
        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/api/v1/org/{$this->orgId}/resources", $resourceData);

        if (!$response->successful()) {
            throw new \RuntimeException(
                "Pangolin: Resource erstellen fehlgeschlagen ({$response->status()}): " . $response->body()
            );
        }

        $body       = $response->json();
        $resourceId = $body['data']['resourceId'] ?? $body['resourceId'] ?? null;

        if (!$resourceId) {
            throw new \RuntimeException('Pangolin: Keine Resource-ID in Antwort: ' . $response->body());
        }

        Log::info("[DomainProvisioner/Pangolin] Resource erstellt: {$domain} (ID: {$resourceId})");

        // 2. Target (Ziel-Server) hinzufügen
        $targetResponse = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/api/v1/org/{$this->orgId}/resources/{$resourceId}/targets", [
                'ip'      => $targetIp,
                'port'    => $targetPort,
                'method'  => 'http',
                'enabled' => true,
            ]);

        if (!$targetResponse->successful()) {
            // Resource aufräumen wenn Target-Anlegen fehlschlägt
            $this->deleteResource((string) $resourceId);
            throw new \RuntimeException(
                "Pangolin: Target hinzufügen fehlgeschlagen ({$targetResponse->status()}): " . $targetResponse->body()
            );
        }

        Log::info("[DomainProvisioner/Pangolin] Target hinzugefügt: {$targetIp}:{$targetPort}");

        return (string) $resourceId;
    }

    /**
     * Löscht eine Pangolin Resource.
     */
    public function deleteResource(string $resourceId): void
    {
        $response = Http::withHeaders($this->headers())
            ->delete("{$this->baseUrl}/api/v1/org/{$this->orgId}/resources/{$resourceId}");

        if (!$response->successful()) {
            throw new \RuntimeException(
                "Pangolin: Resource löschen fehlgeschlagen ({$response->status()}): " . $response->body()
            );
        }
    }

    /**
     * Listet alle Resources (zum Debuggen).
     */
    public function listResources(): array
    {
        $response = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/api/v1/org/{$this->orgId}/resources");

        return $response->json('data', []);
    }

    private function headers(): array
    {
        return [
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }
}
