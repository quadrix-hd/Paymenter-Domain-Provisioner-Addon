<?php

namespace Paymenter\Extensions\Others\Domains2;

use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\Domains2\Models\DomainProvision;
use Paymenter\Extensions\Others\Domains2\Models\DomainSetting;
use Paymenter\Extensions\Others\Domains2\Services\PangolinService;

class Listeners
{
    private const PROPERTY_IP_KEYS = [
        'local_ip',
        'localip',
        'private_ip',
        'ip',
        'ipv4',
        'primary_ip',
    ];

    public static function onServiceCreated($event): void
    {
        try {
            $service = $event->service ?? null;
            if (!$service) return;
            self::provision($service);
        } catch (\Throwable $e) {
            Log::error('[DomainProvisioner] Fehler beim Provisionieren: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }
    }

    public static function onServiceDeleted($event): void
    {
        self::deprovision($event);
    }

    public static function onServiceUpdated($event): void
    {
        $service = $event->service ?? null;
        if ($service && $service->status === 'cancelled') {
            self::deprovision($event);
        }
    }

    private static function provision($service): void
    {
        $serviceId = (string) $service->id;

        if (DomainProvision::where('order_id', $serviceId)->exists()) {
            Log::info("[DomainProvisioner] Service {$serviceId} bereits provisioniert.");
            return;
        }

        // Subdomain aus Properties lesen
        $subdomainField = DomainSetting::get('subdomain_field', 'subdomain');
        $properties     = $service->properties->pluck('value', 'key');
        $subdomain      = $properties[$subdomainField] ?? null;

        if (!$subdomain) {
            Log::warning("[DomainProvisioner] Service {$serviceId}: kein Feld '{$subdomainField}'. Properties: " . $properties->toJson());
            return;
        }

        $serverIp = self::resolveServerIp($service, $properties);

        if (!$serverIp) {
            Log::warning("[DomainProvisioner] Service {$serviceId}: keine IP gefunden.");
            return;
        }

        $subdomain  = trim(strtolower(preg_replace('/[^a-z0-9\-]/', '-', $subdomain)), '-');
        $fullDomain = self::buildDomain($subdomain);
        $targetPort = (int) DomainSetting::get('target_port', 80);

        Log::info("[DomainProvisioner] Provisioniere Pangolin-Resource '{$fullDomain}' -> {$serverIp}:{$targetPort}");

        $pangolin = new PangolinService(
            DomainSetting::get('pangolin_url'),
            DomainSetting::get('pangolin_api_key'),
            DomainSetting::get('pangolin_org_id'),
            DomainSetting::get('pangolin_site_id')
        );
        $pangolinId = $pangolin->createResource($fullDomain, $serverIp, $targetPort);

        DomainProvision::create([
            'order_id'             => $serviceId,
            'full_domain'          => $fullDomain,
            'server_ip'            => $serverIp,
            'pangolin_resource_id' => $pangolinId,
        ]);

        Log::info("[DomainProvisioner] Pangolin-Resource '{$fullDomain}' provisioniert (ID: {$pangolinId})!");
    }

    private static function resolveServerIp(mixed $service, mixed $properties): ?string
    {
        // 1) Primär aus Proxmox-Relation laden.
        try {
            $proxmoxServer = \Paymenter\Extensions\Servers\Proxmox\Models\Server::where('service_id', (string) $service->id)->first();
            if ($proxmoxServer && $proxmoxServer->primary_ipv4) {
                $ipModel = \Paymenter\Extensions\Servers\Proxmox\Models\IPAddress::find($proxmoxServer->primary_ipv4);
                $ip = self::sanitizeIp($ipModel->ip ?? null);
                if ($ip) {
                    return $ip;
                }
            }
        } catch (\Throwable $e) {
            Log::warning("[DomainProvisioner] Proxmox-IP konnte nicht geladen werden: " . $e->getMessage());
        }

        // 2) Fallback: IP direkt aus Service-Properties lesen.
        foreach (self::PROPERTY_IP_KEYS as $key) {
            $ip = self::sanitizeIp($properties[$key] ?? null);
            if ($ip) {
                return $ip;
            }
        }

        return null;
    }

    private static function sanitizeIp(mixed $rawIp): ?string
    {
        if (!is_string($rawIp) || $rawIp === '') {
            return null;
        }

        $candidate = trim($rawIp);
        $candidate = preg_replace('/\/\d+$/', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/:\d+$/', '', $candidate) ?? $candidate;

        return filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)
            ? $candidate
            : null;
    }

    private static function buildDomain(string $subdomain): string
    {
        $suffix = trim((string) DomainSetting::get('domain_suffix', ''));
        if ($suffix === '') {
            return $subdomain;
        }

        $suffix = ltrim(strtolower($suffix), '.');
        if (str_ends_with($subdomain, ".{$suffix}") || $subdomain === $suffix) {
            return $subdomain;
        }

        return "{$subdomain}.{$suffix}";
    }

    private static function deprovision($event): void
    {
        try {
            $service   = $event->service ?? null;
            if (!$service) return;
            $serviceId = (string) $service->id;
            $provision = DomainProvision::where('order_id', $serviceId)->first();
            if (!$provision) return;

            if ($provision->pangolin_resource_id) {
                (new PangolinService(
                    DomainSetting::get('pangolin_url'),
                    DomainSetting::get('pangolin_api_key'),
                    DomainSetting::get('pangolin_org_id')
                ))->deleteResource($provision->pangolin_resource_id);
            }

            $provision->delete();
            Log::info("[DomainProvisioner] Pangolin-Resource '{$provision->full_domain}' deprovisioniert!");
        } catch (\Throwable $e) {
            Log::error('[DomainProvisioner] Fehler beim Deprovisionieren: ' . $e->getMessage());
        }
    }
}
