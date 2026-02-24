<?php

namespace Paymenter\Extensions\Others\Domains2\Admin\Resources;

use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Paymenter\Extensions\Others\Domains2\Admin\Resources\SettingsResource\Pages\ListSettings;
use Paymenter\Extensions\Others\Domains2\Admin\Resources\SettingsResource\Pages\EditSettings;
use Paymenter\Extensions\Others\Domains2\Models\DomainSetting;

class SettingsResource extends Resource
{
    protected static ?string $model = DomainSetting::class;

    protected static string|\BackedEnum|null $navigationIcon = 'ri-global-line';

    protected static string|\UnitEnum|null $navigationGroup = 'Domain Provisioner';

    protected static ?string $label = 'Einstellungen';

    protected static ?string $slug = 'domain-provisioner-settings';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('key')
                ->label('Einstellung')
                ->disabled(),
            Forms\Components\TextInput::make('value')
                ->label('Wert')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->label('Einstellung')->sortable(),
                TextColumn::make('value')->label('Wert')->limit(40),
                TextColumn::make('updated_at')->label('Geändert')->dateTime('d.m.Y H:i'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSettings::route('/'),
            'edit'  => EditSettings::route('/{record}/edit'),
        ];
    }
}
