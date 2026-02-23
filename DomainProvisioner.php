<?php

namespace Paymenter\Extensions\Others\DomainProvisioner;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use Illuminate\Support\Facades\Event;

#[ExtensionMeta(
    name: 'Domain Provisioner',
    description: 'Erstellt automatisch Cloudflare DNS-Einträge und Pangolin-Tunnel wenn ein Kunde eine VM mit Wunsch-Subdomain bestellt.',
    version: '1.0.0',
    author: 'Du',
    url: 'https://github.com',
    icon: 'https://paymenter.org/logo-dark.svg'
)]
class DomainProvisioner extends Extension
{
    /**
     * Wird beim Start geladen (wenn Extension aktiviert ist).
     * Hier registrieren wir unsere Event-Listener.
     */
    public function boot(): void
    {
        // Laravel Events auf Paymenter-Modelle horchen
        Event::listen(\App\Events\Order\OrderCreated::class, [Listeners::class, 'onOrderCreated']);
        Event::listen(\App\Events\Order\OrderActivated::class, [Listeners::class, 'onOrderActivated']);
        Event::listen(\App\Events\Order\OrderCancelled::class, [Listeners::class, 'onOrderCancelled']);
        Event::listen(\App\Events\Order\OrderDeleted::class, [Listeners::class, 'onOrderDeleted']);
    }

    /**
     * Konfigurationsfelder – erscheinen im Paymenter Admin-Panel unter der Extension.
     */
    public function getConfig($values = []): array
    {
        return [
            // ── Basis-Domain ──────────────────────────────────────────────
            [
                'name'        => 'base_domain',
                'label'       => 'Basis-Domain',
                'type'        => 'text',
                'default'     => 'example.com',
                'description' => 'Deine Hauptdomain. Kunden-Subdomains werden darunter angelegt (z.B. kunde.example.com)',
                'required'    => true,
                'validation'  => 'required|string',
            ],

            // ── Cloudflare ────────────────────────────────────────────────
            [
                'name'        => 'cf_api_token',
                'label'       => 'Cloudflare API Token',
                'type'        => 'text',
                'default'     => '',
                'description' => 'Cloudflare Dashboard → Mein Profil → API-Token. Berechtigung: Zone > DNS > Edit',
                'required'    => true,
                'validation'  => 'required|string',
            ],
            [
                'name'        => 'cf_zone_id',
                'label'       => 'Cloudflare Zone-ID',
                'type'        => 'text',
                'default'     => '',
                'description' => 'Cloudflare Dashboard → deine Domain → rechts unten "Zone-ID"',
                'required'    => true,
                'validation'  => 'required|string',
            ],
            [
                'name'        => 'cf_proxied',
                'label'       => 'Cloudflare Proxy aktiv?',
                'type'        => 'select',
                'default'     => '0',
                'description' => 'Aktiviert die Cloudflare "orange Wolke" (DDoS-Schutz). Bei Pangolin-Tunnel empfohlen: Nein',
                'required'    => true,
                'options'     => [
                    '0' => 'Nein (nur DNS, grau)',
                    '1' => 'Ja (Cloudflare Proxy, orange)',
                ],
            ],

            // ── Pangolin ──────────────────────────────────────────────────
            [
                'name'        => 'pangolin_url',
                'label'       => 'Pangolin URL',
                'type'        => 'text',
                'default'     => 'https://pangolin.example.com',
                'description' => 'URL deiner Pangolin-Instanz (kein abschließendes /)',
                'required'    => true,
                'validation'  => 'required|url',
            ],
            [
                'name'        => 'pangolin_api_key',
                'label'       => 'Pangolin API Key',
                'type'        => 'text',
                'default'     => '',
                'description' => 'Pangolin Dashboard → Settings → API Keys',
                'required'    => true,
                'validation'  => 'required|string',
            ],
            [
                'name'        => 'pangolin_org_id',
                'label'       => 'Pangolin Organisation-ID',
                'type'        => 'text',
                'default'     => '1',
                'description' => 'Organisation-ID aus Pangolin (steht in der URL: /org/1/...)',
                'required'    => true,
                'validation'  => 'required|string',
            ],
            [
                'name'        => 'pangolin_site_id',
                'label'       => 'Pangolin Site-ID',
                'type'        => 'text',
                'default'     => '1',
                'description' => 'Site-ID aus Pangolin (steht in der URL: /sites/1/...)',
                'required'    => false,
                'validation'  => 'nullable|string',
            ],

            // ── Produkt-Einstellungen ─────────────────────────────────────
            [
                'name'        => 'subdomain_field',
                'label'       => 'Feldname für Wunsch-Subdomain',
                'type'        => 'text',
                'default'     => 'subdomain',
                'description' => 'Name des Custom-Feldes in Paymenter, in das der Kunde seine Wunsch-Subdomain einträgt',
                'required'    => true,
                'validation'  => 'required|string',
            ],
            [
                'name'        => 'server_ip_field',
                'label'       => 'Feldname für Server-IP',
                'type'        => 'text',
                'default'     => 'server_ip',
                'description' => 'Name des Feldes das die Server-IP enthält (aus Pterodactyl oder Custom-Feld)',
                'required'    => true,
                'validation'  => 'required|string',
            ],
            [
                'name'        => 'target_port',
                'label'       => 'Standard Ziel-Port',
                'type'        => 'text',
                'default'     => '80',
                'description' => 'Port auf dem Kundenserver (meist 80 oder 443)',
                'required'    => true,
                'validation'  => 'required|integer|min:1|max:65535',
            ],
        ];
    }

    /**
     * Wird beim ersten Installieren der Extension ausgeführt.
     */
    public function installed(): void
    {
        ExtensionHelper::runMigrations('extensions/Others/DomainProvisioner/database/migrations');
    }

    /**
     * Wird beim Deinstallieren ausgeführt.
     */
    public function uninstalled(): void
    {
        ExtensionHelper::rollbackMigrations('extensions/Others/DomainProvisioner/database/migrations');
    }

    /**
     * Wird beim Update auf eine neue Version ausgeführt.
     */
    public function upgraded(): void
    {
        ExtensionHelper::runMigrations('extensions/Others/DomainProvisioner/database/migrations');
    }
}
