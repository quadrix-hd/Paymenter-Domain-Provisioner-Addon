<?php

namespace Paymenter\Extensions\Others\DomainProvisioner\Admin\Resources\SettingsResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\DomainProvisioner\Admin\Resources\SettingsResource;

class ListSettings extends ListRecords
{
    protected static string $resource = SettingsResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
