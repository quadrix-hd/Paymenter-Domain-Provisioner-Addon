"""
Paymenter Domain Provisioner
=============================
Empfängt Paymenter Webhooks und:
1. Erstellt einen Cloudflare A-Record (subdomain -> Server-IP)
2. Erstellt einen Pangolin Tunnel-Eintrag für die Domain

Starten: uvicorn main:app --host 0.0.0.0 --port 8000
"""

import hmac
import hashlib
import json
import logging
from typing import Optional

import httpx
from fastapi import FastAPI, Request, HTTPException, BackgroundTasks
from fastapi.responses import JSONResponse

from config import Settings
from cloudflare_dns import create_dns_record, delete_dns_record
from pangolin import create_tunnel_entry, delete_tunnel_entry
from db import Database

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s"
)
logger = logging.getLogger(__name__)

settings = Settings()
db = Database("provisioner.db")
app = FastAPI(title="Domain Provisioner", version="1.0.0")


def verify_webhook_signature(body: bytes, signature: str) -> bool:
    """Prüft die Paymenter Webhook-Signatur (HMAC-SHA256)."""
    if not settings.WEBHOOK_SECRET:
        logger.warning("Kein WEBHOOK_SECRET gesetzt – Signaturprüfung übersprungen!")
        return True
    expected = hmac.new(
        settings.WEBHOOK_SECRET.encode(),
        body,
        hashlib.sha256
    ).hexdigest()
    return hmac.compare_digest(f"sha256={expected}", signature)


@app.post("/webhook/paymenter")
async def paymenter_webhook(request: Request, background_tasks: BackgroundTasks):
    """Hauptendpunkt für Paymenter Webhooks."""
    body = await request.body()
    signature = request.headers.get("X-Signature", "")

    if not verify_webhook_signature(body, signature):
        logger.warning("Ungültige Webhook-Signatur!")
        raise HTTPException(status_code=401, detail="Ungültige Signatur")

    try:
        payload = json.loads(body)
    except json.JSONDecodeError:
        raise HTTPException(status_code=400, detail="Ungültiges JSON")

    event = payload.get("event")
    logger.info(f"Webhook empfangen: {event}")

    if event == "order.created" or event == "order.activated":
        background_tasks.add_task(handle_provision, payload)
    elif event == "order.cancelled" or event == "order.deleted":
        background_tasks.add_task(handle_deprovision, payload)
    else:
        logger.info(f"Unbekanntes Event ignoriert: {event}")

    return JSONResponse({"status": "ok"})


async def handle_provision(payload: dict):
    """Erstellt DNS + Pangolin Eintrag für eine neue Bestellung."""
    try:
        order = payload.get("order", {})
        order_id = str(order.get("id"))
        
        # Wunsch-Subdomain aus den Bestelloptionen lesen
        # Paymenter speichert Custom-Felder meist unter options/metadata
        options = order.get("options", {}) or order.get("metadata", {})
        subdomain = options.get("subdomain") or options.get("domain") or options.get("wunschdomain")
        server_ip = options.get("server_ip") or order.get("server_ip")

        if not subdomain:
            logger.error(f"Keine Subdomain in Bestellung {order_id} gefunden. Payload: {options}")
            return

        if not server_ip:
            logger.error(f"Keine Server-IP in Bestellung {order_id} gefunden.")
            return

        # Subdomain bereinigen (keine Punkte, keine Leerzeichen)
        subdomain = subdomain.strip().lower().replace(" ", "-").split(".")[0]
        full_domain = f"{subdomain}.{settings.BASE_DOMAIN}"

        logger.info(f"Provisioniere Domain: {full_domain} -> {server_ip} (Bestellung: {order_id})")

        # 1. Cloudflare DNS A-Record erstellen
        cf_record_id = await create_dns_record(
            zone_id=settings.CF_ZONE_ID,
            api_token=settings.CF_API_TOKEN,
            name=full_domain,
            ip=server_ip,
            proxied=settings.CF_PROXIED
        )
        logger.info(f"Cloudflare A-Record erstellt: {full_domain} -> {server_ip} (ID: {cf_record_id})")

        # 2. Pangolin Tunnel-Eintrag erstellen
        pangolin_entry_id = await create_tunnel_entry(
            base_url=settings.PANGOLIN_URL,
            api_key=settings.PANGOLIN_API_KEY,
            domain=full_domain,
            target_ip=server_ip,
            target_port=settings.DEFAULT_TARGET_PORT,
            org_id=settings.PANGOLIN_ORG_ID,
            site_id=settings.PANGOLIN_SITE_ID
        )
        logger.info(f"Pangolin Tunnel-Eintrag erstellt für {full_domain} (ID: {pangolin_entry_id})")

        # In DB speichern für späteres Aufräumen
        db.save_provision(
            order_id=order_id,
            full_domain=full_domain,
            server_ip=server_ip,
            cf_record_id=cf_record_id,
            pangolin_entry_id=pangolin_entry_id
        )

        logger.info(f"✅ Domain {full_domain} erfolgreich provisioniert!")

    except Exception as e:
        logger.error(f"Fehler beim Provisionieren: {e}", exc_info=True)


async def handle_deprovision(payload: dict):
    """Löscht DNS + Pangolin Eintrag bei Kündigung."""
    try:
        order = payload.get("order", {})
        order_id = str(order.get("id"))

        entry = db.get_provision(order_id)
        if not entry:
            logger.warning(f"Keine gespeicherte Provision für Bestellung {order_id}")
            return

        logger.info(f"Deprovisioniere Domain: {entry['full_domain']} (Bestellung: {order_id})")

        # 1. Cloudflare DNS-Eintrag löschen
        if entry.get("cf_record_id"):
            await delete_dns_record(
                zone_id=settings.CF_ZONE_ID,
                api_token=settings.CF_API_TOKEN,
                record_id=entry["cf_record_id"]
            )
            logger.info(f"Cloudflare A-Record gelöscht: {entry['full_domain']}")

        # 2. Pangolin Tunnel-Eintrag löschen
        if entry.get("pangolin_entry_id"):
            await delete_tunnel_entry(
                base_url=settings.PANGOLIN_URL,
                api_key=settings.PANGOLIN_API_KEY,
                entry_id=entry["pangolin_entry_id"],
                org_id=settings.PANGOLIN_ORG_ID
            )
            logger.info(f"Pangolin Tunnel-Eintrag gelöscht für {entry['full_domain']}")

        db.delete_provision(order_id)
        logger.info(f"✅ Domain {entry['full_domain']} erfolgreich deprovisioniert!")

    except Exception as e:
        logger.error(f"Fehler beim Deprovisionieren: {e}", exc_info=True)


@app.get("/health")
async def health():
    return {"status": "ok", "provisions": db.count()}


@app.get("/provisions")
async def list_provisions():
    """Zeigt alle aktiven Provisions (nur intern nutzen!)."""
    return db.list_all()
