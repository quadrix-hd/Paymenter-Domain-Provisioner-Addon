<?php

namespace Paymenter\Extensions\Others\Domains2;

use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\Domains2\Models\DomainProvision;
use Paymenter\Extensions\Others\Domains2\Models\DomainSetting;
use Paymenter\Extensions\Others\Domains2\Services\CloudflareService;
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

    public static function onServiceDeleted($event): void { self::deprovision($event); }
    public static function onServiceUpdated($event): void
    {
        // Deprovisionieren wenn Status auf cancelled gesetzt wird
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

        $subdomainField = DomainSetting::get('subdomain_field', 'subdomain');
        $serverIpField  = DomainSetting::get('server_ip_field', 'server_ip');

        // Properties aus dem Service lesen (Custom-Felder)
        $properties = $service->properties->pluck('value', 'key') ?? collect();

        // Fallback: direkt über Relation mit custom_property name
        if ($properties->isEmpty()) {
            $properties = collect();
            foreach ($service->properties as $prop) {
                $key = $prop->parent_property->identifier ?? $prop->key ?? null;
                if ($key) $properties[$key] = $prop->value;
            }
        }

        $subdomain = $properties[$subdomainField] ?? null;
        $serverIp  = $properties[$serverIpField]  ?? null;

        if (!$subdomain) { Log::warning("[DomainProvisioner] Service {$serviceId}: kein Feld '{$subdomainField}'. Properties: " . $properties->toJson()); return; }
        if (!$serverIp)  { Log::warning("[DomainProvisioner] Service {$serviceId}: kein Feld '{$serverIpField}'. Properties: " . $properties->toJson()); return; }

        $subdomain  = trim(strtolower(preg_replace('/[^a-z0-9\-]/', '-', $subdomain)), '-');
        $fullDomain = $subdomain . '.' . DomainSetting::get('base_domain');
        $targetPort = (int) DomainSetting::get('target_port', 80);

        Log::info("[DomainProvisioner] Provisioniere {$fullDomain} → {$serverIp}:{$targetPort}");

        $cf = new CloudflareService(
            DomainSetting::get('cf_api_token'),
            DomainSetting::get('cf_zone_id'),
            (bool) DomainSetting::get('cf_proxied', false)
        );
        $cfRecordId = $cf->createARecord($fullDomain, $serverIp);

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
            'cf_record_id'         => $cfRecordId,
            'pangolin_resource_id' => $pangolinId,
        ]);

        Log::info("[DomainProvisioner] ✅ {$fullDomain} provisioniert!");
    }

    private static function deprovision($event): void
    {
        try {
            $service   = $event->service ?? null;
            if (!$service) return;
            $serviceId = (string) $service->id;
            $provision = DomainProvision::where('order_id', $serviceId)->first();
            if (!$provision) return;

            if ($provision->cf_record_id) {
                (new CloudflareService(DomainSetting::get('cf_api_token'), DomainSetting::get('cf_zone_id')))
                    ->deleteRecord($provision->cf_record_id);
            }
            if ($provision->pangolin_resource_id) {
                (new PangolinService(DomainSetting::get('pangolin_url'), DomainSetting::get('pangolin_api_key'), DomainSetting::get('pangolin_org_id')))
                    ->deleteResource($provision->pangolin_resource_id);
            }

            $provision->delete();
            Log::info("[DomainProvisioner] ✅ {$provision->full_domain} deprovisioniert!");
        } catch (\Throwable $e) {
            Log::error('[DomainProvisioner] Fehler beim Deprovisionieren: ' . $e->getMessage());
        }
    }
}
