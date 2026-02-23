<?php

namespace Paymenter\Extensions\Others\DomainProvisioner;

use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\DomainProvisioner\Services\CloudflareService;
use Paymenter\Extensions\Others\DomainProvisioner\Services\PangolinService;
use Paymenter\Extensions\Others\DomainProvisioner\Models\DomainProvision;
use Paymenter\Extensions\Others\DomainProvisioner\Models\DomainSetting;

class Listeners
{
    private static function setting(string $key, mixed $default = null): mixed
    {
        return DomainSetting::where('key', $key)->value('value') ?? $default;
    }

    public static function onOrderActivated($event): void
    {
        try {
            $order = $event->order ?? $event->model ?? null;
            if (!$order) return;
            self::provision($order);
        } catch (\Throwable $e) {
            Log::error('[DomainProvisioner] Fehler beim Provisionieren: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }
    }

    public static function onOrderCancelled($event): void { self::deprovision($event); }
    public static function onOrderDeleted($event): void   { self::deprovision($event); }

    private static function provision($order): void
    {
        $orderId = (string) $order->id;

        if (DomainProvision::where('order_id', $orderId)->exists()) {
            Log::info("[DomainProvisioner] Bestellung {$orderId} bereits provisioniert.");
            return;
        }

        $subdomainField = self::setting('subdomain_field', 'subdomain');
        $serverIpField  = self::setting('server_ip_field', 'server_ip');

        $options   = is_array($order->options) ? $order->options : json_decode($order->options ?? '{}', true);
        $subdomain = $options[$subdomainField] ?? $order->{$subdomainField} ?? null;
        $serverIp  = $options[$serverIpField]  ?? $order->{$serverIpField}  ?? null;

        if (!$subdomain) { Log::warning("[DomainProvisioner] Bestellung {$orderId}: kein Feld '{$subdomainField}'."); return; }
        if (!$serverIp)  { Log::warning("[DomainProvisioner] Bestellung {$orderId}: kein Feld '{$serverIpField}'."); return; }

        $subdomain  = trim(strtolower(preg_replace('/[^a-z0-9\-]/', '-', $subdomain)), '-');
        $fullDomain = $subdomain . '.' . self::setting('base_domain');
        $targetPort = (int) self::setting('target_port', 80);

        Log::info("[DomainProvisioner] Provisioniere {$fullDomain} → {$serverIp}:{$targetPort}");

        // 1. Cloudflare
        $cf = new CloudflareService(self::setting('cf_api_token'), self::setting('cf_zone_id'), (bool) self::setting('cf_proxied', '0'));
        $cfRecordId = $cf->createARecord($fullDomain, $serverIp);

        // 2. Pangolin
        $pangolin = new PangolinService(self::setting('pangolin_url'), self::setting('pangolin_api_key'), self::setting('pangolin_org_id'), self::setting('pangolin_site_id'));
        $pangolinId = $pangolin->createResource($fullDomain, $serverIp, $targetPort);

        DomainProvision::create(['order_id' => $orderId, 'full_domain' => $fullDomain, 'server_ip' => $serverIp, 'cf_record_id' => $cfRecordId, 'pangolin_resource_id' => $pangolinId]);

        Log::info("[DomainProvisioner] ✅ {$fullDomain} provisioniert!");
    }

    private static function deprovision($event): void
    {
        try {
            $order     = $event->order ?? $event->model ?? null;
            if (!$order) return;
            $orderId   = (string) $order->id;
            $provision = DomainProvision::where('order_id', $orderId)->first();
            if (!$provision) return;

            if ($provision->cf_record_id) {
                $cf = new CloudflareService(self::setting('cf_api_token'), self::setting('cf_zone_id'));
                $cf->deleteRecord($provision->cf_record_id);
            }
            if ($provision->pangolin_resource_id) {
                $pangolin = new PangolinService(self::setting('pangolin_url'), self::setting('pangolin_api_key'), self::setting('pangolin_org_id'));
                $pangolin->deleteResource($provision->pangolin_resource_id);
            }

            $provision->delete();
            Log::info("[DomainProvisioner] ✅ {$provision->full_domain} deprovisioniert!");
        } catch (\Throwable $e) {
            Log::error('[DomainProvisioner] Fehler beim Deprovisionieren: ' . $e->getMessage());
        }
    }
}
