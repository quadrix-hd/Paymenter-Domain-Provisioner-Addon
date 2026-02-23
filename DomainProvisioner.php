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
    public function boot(): void
    {
        // Admin-Seite registrieren (erscheint in der linken Seitenleiste)
        \Filament\Facades\Filament::registerPages([
            \Paymenter\Extensions\Others\DomainProvisioner\Filament\Pages\DomainProvisionerSettings::class,
        ]);

        // Order-Events registrieren
        Event::listen(\App\Events\Order\OrderActivated::class, [Listeners::class, 'onOrderActivated']);
        Event::listen(\App\Events\Order\OrderCancelled::class, [Listeners::class, 'onOrderCancelled']);
        Event::listen(\App\Events\Order\OrderDeleted::class,   [Listeners::class, 'onOrderDeleted']);
    }

    public function installed(): void
    {
        ExtensionHelper::runMigrations('extensions/Others/DomainProvisioner/database/migrations');
    }

    public function uninstalled(): void
    {
        ExtensionHelper::rollbackMigrations('extensions/Others/DomainProvisioner/database/migrations');
    }

    public function upgraded(): void
    {
        ExtensionHelper::runMigrations('extensions/Others/DomainProvisioner/database/migrations');
    }
}
