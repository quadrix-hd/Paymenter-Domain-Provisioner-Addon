<?php

namespace Paymenter\Extensions\Others\Domains2\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PangolinService
{
    public function __construct(
        private string  $baseUrl,
        private string  $apiKey,
        private string  $orgId,
        private ?string $siteId = null,
        private ?string $domainId = null,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function createResource(string $domain, string $targetIp, int $targetPort = 80): string
    {
        $subdomain = explode('.', $domain)[0];

        $resourceData = [
            'name'      => $domain,
            'subdomain' => $subdomain,
            'http'      => true,
            'protocol'  => 'tcp',
            'domainId'  => $this->domainId,
        ];

        Log::info("[DomainProvisioner/Pangolin] Erstelle Resource: " . json_encode($resourceData));

        $response = Http::withHeaders($this->headers())
            ->put("{$this->baseUrl}/v1/org/{$this->orgId}/resource", $resourceData);

        Log::info("[DomainProvisioner/Pangolin] Response ({$response->status()}): " . $response->body());

        if (!$response->successful()) {
            throw new \RuntimeException(
                "Pangolin: Resource erstellen fehlgeschlagen ({$response->status()}): " . $response->body()
            );
        }

        $body       = $response->json();
        $resourceId = $body['data']['resourceId'] ?? null;

        if (!$resourceId) {
            throw new \RuntimeException('Pangolin: Keine Resource-ID in Antwort: ' . $response->body());
        }

        Log::info("[DomainProvisioner/Pangolin] Resource erstellt: {$domain} (ID: {$resourceId})");

        $targetData = [
            'ip'      => $targetIp,
            'port'    => $targetPort,
            'method'  => 'http',
            'enabled' => true,
            'siteId'  => (int) $this->siteId,
        ];

        $targetResponse = Http::withHeaders($this->headers())
            ->put("{$this->baseUrl}/v1/resource/{$resourceId}/target", $targetData);

        Log::info("[DomainProvisioner/Pangolin] Target Response ({$targetResponse->status()}): " . $targetResponse->body());

        if (!$targetResponse->successful()) {
            $this->deleteResource((string) $resourceId);
            throw new \RuntimeException(
                "Pangolin: Target hinzufügen fehlgeschlagen ({$targetResponse->status()}): " . $targetResponse->body()
            );
        }

        Log::info("[DomainProvisioner/Pangolin] Target hinzugefügt: {$targetIp}:{$targetPort}");

        return (string) $resourceId;
    }

    public function deleteResource(string $resourceId): void
    {
        $response = Http::withHeaders($this->headers())
            ->delete("{$this->baseUrl}/v1/resource/{$resourceId}");

        if (!$response->successful()) {
            throw new \RuntimeException(
                "Pangolin: Resource löschen fehlgeschlagen ({$response->status()}): " . $response->body()
            );
        }
    }

    public function listResources(): array
    {
        $response = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/v1/org/{$this->orgId}/resources");

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
