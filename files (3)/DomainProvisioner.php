<?php

namespace Paymenter\Extensions\Others\DomainProvisioner;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\DomainProvisioner\Admin\Resources\SettingsResource;

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
    public function boot(): void
    {
        // Order-Events lauschen
        Event::listen(\App\Events\Order\OrderActivated::class, [Listeners::class, 'onOrderActivated']);
        Event::listen(\App\Events\Order\OrderCancelled::class,  [Listeners::class, 'onOrderCancelled']);
        Event::listen(\App\Events\Order\OrderDeleted::class,    [Listeners::class, 'onOrderDeleted']);
    }

    public function installed(): void
    {
        ExtensionHelper::runMigrations('extensions/Others/DomainProvisioner/database/migrations');
    }

    public function uninstalled(): void
    {
        ExtensionHelper::rollbackMigrations('extensions/Others/DomainProvisioner/database/migrations');
    }

    public function upgraded($oldVersion = null): void
    {
        ExtensionHelper::runMigrations('extensions/Others/DomainProvisioner/database/migrations');
    }

    /**
     * Zeigt im Extension-Tab einen Link zur Einstellungsseite – genau wie Proxmox es macht.
     */
    public function getConfig($values = []): array
    {
        try {
            return [
                [
                    'name'  => 'notice',
                    'type'  => 'placeholder',
                    'label' => new HtmlString(
                        'Domain Provisioner ist aktiv. Klicke <a class="text-primary-600 underline" href="' .
                        SettingsResource::getUrl() .
                        '">hier</a> um die Einstellungen zu öffnen.'
                    ),
                ],
            ];
        } catch (\Exception $e) {
            return [];
        }
    }
}
