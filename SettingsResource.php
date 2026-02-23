<?php

namespace Paymenter\Extensions\Others\DomainProvisioner\Admin\Resources;

use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Paymenter\Extensions\Others\DomainProvisioner\Admin\Resources\SettingsResource\Pages\ListSettings;
use Paymenter\Extensions\Others\DomainProvisioner\Admin\Resources\SettingsResource\Pages\EditSettings;
use Paymenter\Extensions\Others\DomainProvisioner\Models\DomainSetting;

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

            Forms\Components\Section::make('🌐 Basis-Domain')
                ->schema([
                    Forms\Components\TextInput::make('base_domain')
                        ->label('Basis-Domain')
                        ->placeholder('meinedomain.de')
                        ->helperText('Kunden-Subdomains werden darunter angelegt, z.B. kunde.meinedomain.de')
                        ->required(),
                ]),

            Forms\Components\Section::make('☁️ Cloudflare')
                ->schema([
                    Forms\Components\TextInput::make('cf_api_token')
                        ->label('Cloudflare API Token')
                        ->password()
                        ->helperText('Cloudflare Dashboard → Mein Profil → API-Token (Berechtigung: Zone > DNS > Edit)')
                        ->required(),
                    Forms\Components\TextInput::make('cf_zone_id')
                        ->label('Cloudflare Zone-ID')
                        ->helperText('Cloudflare Dashboard → deine Domain → rechts unten "Zone-ID"')
                        ->required(),
                    Forms\Components\Toggle::make('cf_proxied')
                        ->label('Cloudflare Proxy aktiv?')
                        ->helperText('Aktiviert die orange Wolke (DDoS-Schutz). Bei Pangolin-Tunnel: Nein empfohlen.')
                        ->default(false),
                ]),

            Forms\Components\Section::make('🦎 Pangolin')
                ->schema([
                    Forms\Components\TextInput::make('pangolin_url')
                        ->label('Pangolin URL')
                        ->placeholder('https://pangolin.meinedomain.de')
                        ->helperText('URL deiner Pangolin-Instanz (kein abschließendes /)')
                        ->url()
                        ->required(),
                    Forms\Components\TextInput::make('pangolin_api_key')
                        ->label('Pangolin API Key')
                        ->password()
                        ->helperText('Pangolin Dashboard → Settings → API Keys')
                        ->required(),
                    Forms\Components\TextInput::make('pangolin_org_id')
                        ->label('Pangolin Organisation-ID')
                        ->placeholder('1')
                        ->helperText('Aus der Pangolin-URL: /org/1/ → 1')
                        ->required(),
                    Forms\Components\TextInput::make('pangolin_site_id')
                        ->label('Pangolin Site-ID')
                        ->placeholder('1')
                        ->helperText('Aus der Pangolin-URL: /sites/1/ → 1 (optional)'),
                ]),

            Forms\Components\Section::make('⚙️ Feldnamen & Port')
                ->schema([
                    Forms\Components\TextInput::make('subdomain_field')
                        ->label('Custom-Feld für Wunsch-Subdomain')
                        ->default('subdomain')
                        ->helperText('Name des Custom-Feldes im Paymenter-Produkt')
                        ->required(),
                    Forms\Components\TextInput::make('server_ip_field')
                        ->label('Custom-Feld für Server-IP')
                        ->default('server_ip')
                        ->helperText('Name des Feldes das die Server-IP enthält')
                        ->required(),
                    Forms\Components\TextInput::make('target_port')
                        ->label('Standard Ziel-Port')
                        ->default('80')
                        ->numeric()
                        ->helperText('Port auf dem Kundenserver (meist 80 oder 443)')
                        ->required(),
                ]),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->label('Einstellung')->sortable(),
                TextColumn::make('value')->label('Wert')->limit(40),
                TextColumn::make('updated_at')->label('Zuletzt geändert')->dateTime('d.m.Y H:i'),
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
