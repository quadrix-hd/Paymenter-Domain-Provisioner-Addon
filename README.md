# Domain Provisioner – Paymenter Extension

Automatische DNS- und Tunnel-Provisionierung direkt in Paymenter.  
Wenn ein Kunde eine VM mit Wunsch-Subdomain kauft, wird **automatisch** ein Cloudflare A-Record und ein Pangolin Tunnel-Eintrag erstellt.

---

## Was passiert automatisch?

```
Kunde kauft VM, gibt "meinserver" als Wunsch-Subdomain ein
        ↓
Paymenter aktiviert die Bestellung
        ↓
① Cloudflare A-Record erstellt: meinserver.deinedomain.de → Server-IP
② Pangolin Tunnel-Eintrag erstellt: meinserver.deinedomain.de → Server-IP:Port
        ↓
Kunde kann sofort seine Domain nutzen ✅

Bei Kündigung → alles wird automatisch wieder gelöscht.
```

---

## Installation

### 1. Dateien kopieren

Kopiere den Ordner `DomainProvisioner` in dein Paymenter-Verzeichnis:

```bash
cp -r DomainProvisioner /var/www/paymenter/extensions/Others/
```

### 2. Extension aktivieren

Im Paymenter Admin-Panel:
**Admin → Extensions → Others → Domain Provisioner → Installieren → Aktivieren**

### 3. Extension konfigurieren

Nach dem Aktivieren erscheinen die Konfigurationsfelder direkt im Admin-Panel.  
Trage dort ein:

| Feld | Wo finden |
|---|---|
| **Basis-Domain** | Deine Domain, z.B. `meinedomain.de` |
| **Cloudflare API Token** | Cloudflare Dashboard → Mein Profil → API-Token (Berechtigung: Zone > DNS > Edit) |
| **Cloudflare Zone-ID** | Cloudflare Dashboard → deine Domain → rechts unten |
| **Cloudflare Proxy** | Nein empfohlen bei Pangolin-Tunnel |
| **Pangolin URL** | URL deiner Pangolin-Instanz |
| **Pangolin API Key** | Pangolin Dashboard → Settings → API Keys |
| **Pangolin Org-ID** | Aus der Pangolin-URL: `/org/1/...` → `1` |
| **Pangolin Site-ID** | Aus der Pangolin-URL: `/sites/1/...` → `1` |

### 4. Custom-Feld in Paymenter anlegen

Damit der Kunde seine Wunsch-Subdomain eingeben kann, muss beim VM-Produkt ein Custom-Feld angelegt werden:

**Admin → Produkte → dein VM-Produkt → Custom Fields → Neu**

- **Feldname (intern):** `subdomain`  ← muss mit dem Wert in der Extension-Konfig übereinstimmen!
- **Label:** `Wunsch-Subdomain`
- **Typ:** Text
- **Pflichtfeld:** Ja
- **Beschreibung:** z.B. `Nur Kleinbuchstaben und Bindestrich erlaubt (z.B. "meinserver")`

Für die Server-IP: Wenn Pterodactyl die IP automatisch setzt, prüfe wie Paymenter sie im Order-Objekt speichert. Alternativ auch ein Custom-Feld `server_ip` anlegen (oder den Extension-Feldnamen entsprechend anpassen).

---

## Datei-Übersicht

```
DomainProvisioner/
├── DomainProvisioner.php              # Haupt-Extension-Klasse (Boot + Konfiguration)
├── Listeners.php                      # Event-Listener für Order-Events
├── Services/
│   ├── CloudflareService.php          # Cloudflare DNS API
│   └── PangolinService.php            # Pangolin Tunnel API
├── Models/
│   └── DomainProvision.php            # Eloquent Model für DB-Einträge
├── database/
│   └── migrations/
│       └── ..._create_domain_provisions_table.php
└── README.md
```

---

## Troubleshooting

**Logs prüfen:**
```bash
tail -f /var/www/paymenter/storage/logs/laravel.log | grep DomainProvisioner
```

**Migration manuell ausführen** (falls nötig):
```bash
cd /var/www/paymenter
php artisan migrate
```

**Cloudflare API Token testen:**
```bash
curl -X GET "https://api.cloudflare.com/client/v4/zones/ZONE_ID/dns_records" \
  -H "Authorization: Bearer DEIN_TOKEN" \
  -H "Content-Type: application/json"
```

**Pangolin API testen:**
```bash
curl -X GET "https://pangolin.deinedomain.de/api/v1/org/1/resources" \
  -H "Authorization: Bearer DEIN_API_KEY"
```

---

## Hinweis zu Paymenter Events

Die Extension lauscht auf folgende Paymenter-Events:
- `App\Events\Order\OrderActivated` → Provisionieren
- `App\Events\Order\OrderCancelled` → Deprovisionieren
- `App\Events\Order\OrderDeleted` → Deprovisionieren

Falls deine Paymenter-Version andere Event-Namen verwendet, prüfe die Event-Klassen in `/var/www/paymenter/app/Events/` und passe `Listeners.php` entsprechend an.
