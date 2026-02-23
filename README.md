# Domain Provisioner – Paymenter Extension v2

## Installation

```bash

# Ordner kopieren
cp -r Paymenter-Domain-Provisioner-Addon /var/www/paymenter/extensions/Others/
mv Paymenter-Domain-Provisioner-Addon Domain-Provisioner

# Extension erstellen
cd /var/www/paymenter
php artisan app:extension:create

# Cache leeren
cd /var/www/paymenter
php artisan optimize:clear
```

Dann im Admin-Panel: **Extensions → Others → Domain Provisioner → Installieren → Aktivieren**

## Wo sind die Einstellungen?

Nach dem Aktivieren erscheint in der **linken Seitenleiste** unter **Extensions** ein neuer Eintrag:  
**🌐 Domain Provisioner**

Dort kannst du alle Einstellungen direkt eingeben und speichern. Außerdem siehst du dort eine Tabelle aller aktiv provisionierten Domains.

## Custom-Feld im Produkt anlegen

Damit der Kunde seine Wunsch-Subdomain eingeben kann:  
**Admin → Produkte → dein VM-Produkt → Custom Fields → Neu**

- **Feldname:** `subdomain`
- **Label:** Wunsch-Subdomain  
- **Typ:** Text  
- **Pflichtfeld:** Ja

## Logs prüfen

```bash
tail -f /var/www/paymenter/storage/logs/laravel.log | grep DomainProvisioner
```
