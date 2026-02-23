<?php

namespace Paymenter\Extensions\Others\DomainProvisioner\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Paymenter\Extensions\Others\DomainProvisioner\Models\DomainSetting;
use Paymenter\Extensions\Others\DomainProvisioner\Models\DomainProvision;

class DomainProvisionerSettings extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-globe-alt';
    protected static ?string $navigationLabel = 'Domain Provisioner';
    protected static ?string $navigationGroup = 'Extensions';
    protected static ?int    $navigationSort  = 99;
    protected static string  $view            = 'domain-provisioner::settings';

    // Formular-Daten
    public ?string $base_domain      = null;
    public ?string $cf_api_token     = null;
    public ?string $cf_zone_id       = null;
    public string  $cf_proxied       = '0';
    public ?string $pangolin_url     = null;
    public ?string $pangolin_api_key = null;
    public ?string $pangolin_org_id  = null;
    public ?string $pangolin_site_id = null;
    public ?string $subdomain_field  = 'subdomain';
    public ?string $server_ip_field  = 'server_ip';
    public string  $target_port      = '80';

    public function mount(): void
    {
        // Gespeicherte Einstellungen laden
        $settings = DomainSetting::all()->pluck('value', 'key');

        $this->base_domain      = $settings->get('base_domain');
        $this->cf_api_token     = $settings->get('cf_api_token');
        $this->cf_zone_id       = $settings->get('cf_zone_id');
        $this->cf_proxied       = $settings->get('cf_proxied', '0');
        $this->pangolin_url     = $settings->get('pangolin_url');
        $this->pangolin_api_key = $settings->get('pangolin_api_key');
        $this->pangolin_org_id  = $settings->get('pangolin_org_id');
        $this->pangolin_site_id = $settings->get('pangolin_site_id');
        $this->subdomain_field  = $settings->get('subdomain_field', 'subdomain');
        $this->server_ip_field  = $settings->get('server_ip_field', 'server_ip');
        $this->target_port      = $settings->get('target_port', '80');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\Section::make('🌐 Basis-Einstellungen')
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
                            ->placeholder('Token hier einfügen')
                            ->helperText('Cloudflare Dashboard → Mein Profil → API-Token. Berechtigung: Zone > DNS > Edit')
                            ->required(),
                        Forms\Components\TextInput::make('cf_zone_id')
                            ->label('Cloudflare Zone-ID')
                            ->placeholder('abc123...')
                            ->helperText('Cloudflare Dashboard → deine Domain → rechts unten "Zone-ID"')
                            ->required(),
                        Forms\Components\Select::make('cf_proxied')
                            ->label('Cloudflare Proxy aktiv?')
                            ->options([
                                '0' => 'Nein – nur DNS (grau, empfohlen bei Pangolin)',
                                '1' => 'Ja – Cloudflare Proxy (orange Wolke)',
                            ])
                            ->default('0')
                            ->required(),
                    ]),

                Forms\Components\Section::make('🦎 Pangolin')
                    ->schema([
                        Forms\Components\TextInput::make('pangolin_url')
                            ->label('Pangolin URL')
                            ->placeholder('https://pangolin.meinedomain.de')
                            ->helperText('URL deiner Pangolin-Instanz, kein abschließendes /')
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

                Forms\Components\Section::make('⚙️ Feldnamen & Ports')
                    ->schema([
                        Forms\Components\TextInput::make('subdomain_field')
                            ->label('Custom-Feld für Wunsch-Subdomain')
                            ->default('subdomain')
                            ->helperText('Name des Custom-Feldes im Paymenter-Produkt, in das der Kunde seine Subdomain einträgt')
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
                            ->minValue(1)
                            ->maxValue(65535)
                            ->helperText('Port auf dem Kundenserver (meist 80 oder 443)')
                            ->required(),
                    ]),

            ])
            ->statePath(''); // Bindet ans $this direkt
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Einstellungen speichern')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $fields = [
            'base_domain', 'cf_api_token', 'cf_zone_id', 'cf_proxied',
            'pangolin_url', 'pangolin_api_key', 'pangolin_org_id', 'pangolin_site_id',
            'subdomain_field', 'server_ip_field', 'target_port',
        ];

        foreach ($fields as $field) {
            DomainSetting::updateOrCreate(
                ['key' => $field],
                ['value' => $this->{$field}]
            );
        }

        Notification::make()
            ->title('Einstellungen gespeichert ✅')
            ->success()
            ->send();
    }

    // Tabelle aktiver Provisions anzeigen
    public function getProvisions(): \Illuminate\Database\Eloquent\Collection
    {
        return DomainProvision::orderByDesc('created_at')->get();
    }
}
