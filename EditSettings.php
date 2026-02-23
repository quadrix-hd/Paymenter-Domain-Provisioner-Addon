<?php

namespace Paymenter\Extensions\Others\DomainProvisioner\Admin\Resources\SettingsResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Paymenter\Extensions\Others\DomainProvisioner\Admin\Resources\SettingsResource;

class EditSettings extends EditRecord
{
    protected static string $resource = SettingsResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
