<?php

namespace Paymenter\Extensions\Others\Domains2;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\Domains2\Admin\Resources\SettingsResource;

#[ExtensionMeta(
    name: 'Domain Provisioner',
    description: 'Erstellt automatisch Cloudflare DNS-Einträge und Pangolin-Tunnel wenn ein Kunde eine VM mit Wunsch-Subdomain bestellt.',
    version: '1.0.0',
    author: 'Du',
    url: 'https://github.com',
    icon: 'https://paymenter.org/logo-dark.svg'
)]
class Domains2 extends Extension
{
    public function boot(): void
    {
        Event::listen(\App\Events\Service\Created::class, [Listeners::class, 'onServiceCreated']);
        Event::listen(\App\Events\Service\Deleted::class, [Listeners::class, 'onServiceDeleted']);
        Event::listen(\App\Events\Service\Updated::class, [Listeners::class, 'onServiceUpdated']);
    }

    public function installed(): void
    {
        ExtensionHelper::runMigrations('extensions/Others/Domains2/database/migrations');
    }

    public function uninstalled(): void
    {
        ExtensionHelper::rollbackMigrations('extensions/Others/Domains2/database/migrations');
    }

    public function upgraded($oldVersion = null): void
    {
        ExtensionHelper::runMigrations('extensions/Others/Domains2/database/migrations');
    }

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
