# Domain Provisioner für Paymenter + Cloudflare + Pangolin

Automatische DNS- und Tunnel-Provisionierung wenn ein Kunde in Paymenter eine Wunsch-Subdomain bucht.

## Was passiert automatisch?

```
Kunde bucht VM mit Wunsch-Subdomain "meinserver"
        ↓
Paymenter sendet Webhook → dieser Server
        ↓
① Cloudflare A-Record erstellen: meinserver.deinedomain.de → Server-IP
② Pangolin Tunnel-Eintrag erstellen: meinserver.deinedomain.de → Server-IP:Port
        ↓
Kunde kann sofort seine Domain nutzen ✅
```

Bei Kündigung werden beide Einträge automatisch wieder gelöscht.

---

## Setup

### 1. Dateien vorbereiten

```bash
git clone <repo> domain-provisioner
cd domain-provisioner
cp .env.example .env
nano .env   # Alle Werte ausfüllen!
```

### 2. Konfiguration (.env)

| Variable | Beschreibung |
|---|---|
| `WEBHOOK_SECRET` | Secret aus Paymenter Webhook-Einstellungen |
| `BASE_DOMAIN` | Deine Domain (z.B. `meinedomain.de`) |
| `CF_API_TOKEN` | Cloudflare API Token (DNS:Edit) |
| `CF_ZONE_ID` | Zone-ID aus Cloudflare Dashboard |
| `CF_PROXIED` | `true` = orange Wolke, `false` = nur DNS |
| `PANGOLIN_URL` | URL deiner Pangolin-Instanz |
| `PANGOLIN_API_KEY` | Pangolin API Key |
| `PANGOLIN_ORG_ID` | Pangolin Organisation-ID |
| `PANGOLIN_SITE_ID` | Pangolin Site-ID |

### 3. Starten

**Mit Docker (empfohlen):**
```bash
docker-compose up -d
docker-compose logs -f
```

**Ohne Docker:**
```bash
pip install -r requirements.txt
uvicorn main:app --host 0.0.0.0 --port 8000
```

### 4. Paymenter Webhook einrichten

In Paymenter unter **Einstellungen → Webhooks → Neu**:

- **URL:** `https://deinserver.de:8000/webhook/paymenter`
- **Events:** `order.created`, `order.activated`, `order.cancelled`, `order.deleted`
- **Secret:** gleicher Wert wie `WEBHOOK_SECRET` in der .env

### 5. Paymenter Bestellformular – Custom-Feld hinzufügen

In Paymenter musst du beim Produkt (VM) ein Custom-Feld anlegen, damit der Kunde seine Wunsch-Subdomain eingeben kann:

- **Feldname:** `subdomain` (oder `domain` oder `wunschdomain`)
- **Typ:** Text
- **Pflichtfeld:** Ja
- **Beschreibung:** z.B. "Deine Wunsch-Subdomain (nur Kleinbuchstaben, keine Punkte)"

Der Wert wird dann im Webhook-Payload unter `order.options.subdomain` übertragen.

---

## API Endpoints

| Endpoint | Methode | Beschreibung |
|---|---|---|
| `/webhook/paymenter` | POST | Paymenter Webhook empfangen |
| `/health` | GET | Status + Anzahl aktiver Provisions |
| `/provisions` | GET | Alle aktiven Domains anzeigen |

---

## Nginx Reverse Proxy (optional)

Wenn du den Server hinter Nginx betreiben willst:

```nginx
server {
    listen 443 ssl;
    server_name provisioner.meinedomain.de;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

---

## Troubleshooting

**Webhook kommt nicht an?**
```bash
# Logs prüfen
docker-compose logs -f provisioner

# Manuell testen
curl -X POST http://localhost:8000/webhook/paymenter \
  -H "Content-Type: application/json" \
  -d '{"event":"order.activated","order":{"id":"test123","options":{"subdomain":"testvm","server_ip":"1.2.3.4"}}}'
```

**Cloudflare Fehler?**
- API Token muss `Zone > DNS > Edit` Berechtigung haben
- Zone-ID muss zur BASE_DOMAIN passen

**Pangolin Fehler?**
- API Key in Pangolin unter Settings > API Keys erstellen
- Org-ID und Site-ID aus der Pangolin URL ablesen (z.B. `/org/1/sites/1`)

---

## Datei-Übersicht

```
domain-provisioner/
├── main.py          # FastAPI Server + Webhook Handler
├── config.py        # Konfiguration (liest .env)
├── cloudflare_dns.py # Cloudflare DNS API
├── pangolin.py      # Pangolin Tunnel API
├── db.py            # SQLite Datenbank
├── .env.example     # Vorlage für Konfiguration
├── requirements.txt
├── Dockerfile
└── docker-compose.yml
```
