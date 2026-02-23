<?php

namespace Paymenter\Extensions\Others\DomainProvisioner;

use App\Helpers\ExtensionHelper;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\DomainProvisioner\Services\CloudflareService;
use Paymenter\Extensions\Others\DomainProvisioner\Services\PangolinService;
use Paymenter\Extensions\Others\DomainProvisioner\Models\DomainProvision;

class Listeners
{
    /**
     * Hilfsfunktion: Konfigurationswert der Extension lesen.
     */
    private static function config(string $key): mixed
    {
        return ExtensionHelper::getConfig('DomainProvisioner', $key);
    }

    /**
     * Bestellung erstellt oder aktiviert → Domain provisionieren.
     */
    public static function onOrderCreated($event): void
    {
        // Nur auf "activated" reagieren damit die Zahlung schon durch ist
        // onOrderActivated übernimmt das
    }

    public static function onOrderActivated($event): void
    {
        try {
            $order = $event->order ?? $event->model ?? null;
            if (!$order) return;

            self::provision($order);
        } catch (\Throwable $e) {
            Log::error('[DomainProvisioner] Fehler beim Provisionieren: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Bestellung gekündigt oder gelöscht → Domain deprovisionieren.
     */
    public static function onOrderCancelled($event): void
    {
        self::deprovision($event);
    }

    public static function onOrderDeleted($event): void
    {
        self::deprovision($event);
    }

    // ──────────────────────────────────────────────────────────────────────────

    private static function provision($order): void
    {
        $orderId = (string) $order->id;

        // Bereits provisioniert? Dann überspringen.
        if (DomainProvision::where('order_id', $orderId)->exists()) {
            Log::info("[DomainProvisioner] Bestellung {$orderId} ist bereits provisioniert.");
            return;
        }

        // Custom-Felder aus der Bestellung lesen
        $subdomainField = self::config('subdomain_field') ?: 'subdomain';
        $serverIpField  = self::config('server_ip_field') ?: 'server_ip';

        // Paymenter speichert Custom-Felder im JSON-Feld "options" oder "metadata"
        $options   = is_array($order->options) ? $order->options : json_decode($order->options ?? '{}', true);
        $subdomain = $options[$subdomainField] ?? null;
        $serverIp  = $options[$serverIpField]  ?? null;

        // Fallback: direkt auf dem Order-Objekt suchen
        if (!$subdomain) $subdomain = $order->{$subdomainField} ?? null;
        if (!$serverIp)  $serverIp  = $order->{$serverIpField}  ?? null;

        if (!$subdomain) {
            Log::warning("[DomainProvisioner] Bestellung {$orderId}: Kein Subdomain-Feld '{$subdomainField}' gefunden.");
            return;
        }
        if (!$serverIp) {
            Log::warning("[DomainProvisioner] Bestellung {$orderId}: Keine Server-IP im Feld '{$serverIpField}' gefunden.");
            return;
        }

        // Subdomain bereinigen
        $subdomain  = strtolower(preg_replace('/[^a-z0-9\-]/', '-', trim($subdomain)));
        $subdomain  = trim($subdomain, '-');
        $baseDomain = self::config('base_domain');
        $fullDomain = "{$subdomain}.{$baseDomain}";
        $targetPort = (int)(self::config('target_port') ?: 80);

        Log::info("[DomainProvisioner] Provisioniere: {$fullDomain} → {$serverIp}:{$targetPort} (Bestellung: {$orderId})");

        // 1. Cloudflare DNS A-Record erstellen
        $cfService = new CloudflareService(
            apiToken: self::config('cf_api_token'),
            zoneId:   self::config('cf_zone_id'),
            proxied:  (bool)(self::config('cf_proxied') ?? false),
        );
        $cfRecordId = $cfService->createARecord($fullDomain, $serverIp);
        Log::info("[DomainProvisioner] Cloudflare A-Record erstellt: {$fullDomain} → {$serverIp} (ID: {$cfRecordId})");

        // 2. Pangolin Tunnel-Eintrag erstellen
        $pangolinService = new PangolinService(
            baseUrl: self::config('pangolin_url'),
            apiKey:  self::config('pangolin_api_key'),
            orgId:   self::config('pangolin_org_id'),
            siteId:  self::config('pangolin_site_id'),
        );
        $pangolinResourceId = $pangolinService->createResource($fullDomain, $serverIp, $targetPort);
        Log::info("[DomainProvisioner] Pangolin Resource erstellt: {$fullDomain} (ID: {$pangolinResourceId})");

        // In DB speichern für späteres Aufräumen
        DomainProvision::create([
            'order_id'            => $orderId,
            'full_domain'         => $fullDomain,
            'server_ip'           => $serverIp,
            'cf_record_id'        => $cfRecordId,
            'pangolin_resource_id' => $pangolinResourceId,
        ]);

        Log::info("[DomainProvisioner] ✅ {$fullDomain} erfolgreich provisioniert!");
    }

    private static function deprovision($event): void
    {
        try {
            $order = $event->order ?? $event->model ?? null;
            if (!$order) return;

            $orderId   = (string) $order->id;
            $provision = DomainProvision::where('order_id', $orderId)->first();

            if (!$provision) {
                Log::info("[DomainProvisioner] Keine Provision für Bestellung {$orderId} gefunden.");
                return;
            }

            Log::info("[DomainProvisioner] Deprovisioniere: {$provision->full_domain} (Bestellung: {$orderId})");

            // 1. Cloudflare DNS-Eintrag löschen
            if ($provision->cf_record_id) {
                $cfService = new CloudflareService(
                    apiToken: self::config('cf_api_token'),
                    zoneId:   self::config('cf_zone_id'),
                );
                $cfService->deleteRecord($provision->cf_record_id);
                Log::info("[DomainProvisioner] Cloudflare A-Record gelöscht: {$provision->full_domain}");
            }

            // 2. Pangolin Resource löschen
            if ($provision->pangolin_resource_id) {
                $pangolinService = new PangolinService(
                    baseUrl: self::config('pangolin_url'),
                    apiKey:  self::config('pangolin_api_key'),
                    orgId:   self::config('pangolin_org_id'),
                );
                $pangolinService->deleteResource($provision->pangolin_resource_id);
                Log::info("[DomainProvisioner] Pangolin Resource gelöscht: {$provision->full_domain}");
            }

            $provision->delete();
            Log::info("[DomainProvisioner] ✅ {$provision->full_domain} erfolgreich deprovisioniert!");

        } catch (\Throwable $e) {
            Log::error('[DomainProvisioner] Fehler beim Deprovisionieren: ' . $e->getMessage());
        }
    }
}
