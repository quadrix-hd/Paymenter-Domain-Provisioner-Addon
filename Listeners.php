<?php

namespace Paymenter\Extensions\Others\Domains2;

use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\Domains2\Models\DomainProvision;
use Paymenter\Extensions\Others\Domains2\Models\DomainSetting;
use Paymenter\Extensions\Others\Domains2\Services\PangolinService;

class Listeners
{
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

        // IP aus Proxmox-Model holen
        $serverIp = null;
        try {
            $proxmoxServer = \Paymenter\Extensions\Servers\Proxmox\Models\Server::where('service_id', $serviceId)->first();
            if ($proxmoxServer && $proxmoxServer->primary_ipv4) {
                $ipModel  = \Paymenter\Extensions\Servers\Proxmox\Models\IPAddress::find($proxmoxServer->primary_ipv4);
                $serverIp = $ipModel->ip ?? null;
            }
        } catch (\Throwable $e) {
            Log::warning("[DomainProvisioner] Proxmox-IP konnte nicht geladen werden: " . $e->getMessage());
        }

        if (!$serverIp) {
            Log::warning("[DomainProvisioner] Service {$serviceId}: keine IP gefunden.");
            return;
        }

        $subdomain  = trim(strtolower(preg_replace('/[^a-z0-9\-]/', '-', $subdomain)), '-');
        $targetPort = (int) DomainSetting::get('target_port', 80);

        Log::info("[DomainProvisioner] Provisioniere Pangolin-Resource '{$subdomain}' -> {$serverIp}:{$targetPort}");

        $pangolin = new PangolinService(
            DomainSetting::get('pangolin_url'),
            DomainSetting::get('pangolin_api_key'),
            DomainSetting::get('pangolin_org_id'),
            DomainSetting::get('pangolin_site_id')
        );
        $pangolinId = $pangolin->createResource($subdomain, $serverIp, $targetPort);

        DomainProvision::create([
            'order_id'             => $serviceId,
            'full_domain'          => $subdomain,
            'server_ip'            => $serverIp,
            'cf_record_id'         => null,
            'pangolin_resource_id' => $pangolinId,
        ]);

        Log::info("[DomainProvisioner] Pangolin-Resource '{$subdomain}' provisioniert (ID: {$pangolinId})!");
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
