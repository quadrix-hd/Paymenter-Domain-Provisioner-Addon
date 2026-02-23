<?php

namespace Paymenter\Extensions\Others\DomainProvisioner;

use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\DomainProvisioner\Models\DomainProvision;
use Paymenter\Extensions\Others\DomainProvisioner\Models\DomainSetting;
use Paymenter\Extensions\Others\DomainProvisioner\Services\CloudflareService;
use Paymenter\Extensions\Others\DomainProvisioner\Services\PangolinService;

class Listeners
{
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

        $subdomainField = DomainSetting::get('subdomain_field', 'subdomain');
        $serverIpField  = DomainSetting::get('server_ip_field', 'server_ip');
        $options        = is_array($order->options) ? $order->options : json_decode($order->options ?? '{}', true);
        $subdomain      = $options[$subdomainField] ?? $order->{$subdomainField} ?? null;
        $serverIp       = $options[$serverIpField]  ?? $order->{$serverIpField}  ?? null;

        if (!$subdomain) { Log::warning("[DomainProvisioner] Bestellung {$orderId}: kein Feld '{$subdomainField}'."); return; }
        if (!$serverIp)  { Log::warning("[DomainProvisioner] Bestellung {$orderId}: kein Feld '{$serverIpField}'."); return; }

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
            'order_id'            => $orderId,
            'full_domain'         => $fullDomain,
            'server_ip'           => $serverIp,
            'cf_record_id'        => $cfRecordId,
            'pangolin_resource_id' => $pangolinId,
        ]);

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
