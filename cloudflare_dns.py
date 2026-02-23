"""
Cloudflare DNS – erstellt und löscht A-Records über die Cloudflare API v4.
"""

import logging
import httpx

logger = logging.getLogger(__name__)

CF_API_BASE = "https://api.cloudflare.com/client/v4"


async def create_dns_record(
    zone_id: str,
    api_token: str,
    name: str,
    ip: str,
    proxied: bool = False,
    ttl: int = 1  # 1 = Auto bei Cloudflare
) -> str:
    """
    Erstellt einen A-Record in Cloudflare.
    Gibt die Record-ID zurück.
    """
    url = f"{CF_API_BASE}/zones/{zone_id}/dns_records"
    headers = {
        "Authorization": f"Bearer {api_token}",
        "Content-Type": "application/json"
    }
    data = {
        "type": "A",
        "name": name,
        "content": ip,
        "ttl": ttl,
        "proxied": proxied
    }

    async with httpx.AsyncClient(timeout=15) as client:
        response = await client.post(url, headers=headers, json=data)

    result = response.json()

    if not result.get("success"):
        errors = result.get("errors", [])
        # Falls Record schon existiert, versuche Update
        if any(e.get("code") == 81057 for e in errors):
            logger.warning(f"A-Record {name} existiert bereits – führe Update durch.")
            return await update_dns_record(zone_id, api_token, name, ip, proxied, ttl)
        raise RuntimeError(f"Cloudflare Fehler beim Erstellen: {errors}")

    return result["result"]["id"]


async def update_dns_record(
    zone_id: str,
    api_token: str,
    name: str,
    ip: str,
    proxied: bool = False,
    ttl: int = 1
) -> str:
    """Sucht einen bestehenden A-Record und aktualisiert ihn."""
    record_id = await find_dns_record(zone_id, api_token, name)
    if not record_id:
        raise RuntimeError(f"A-Record {name} nicht gefunden für Update.")

    url = f"{CF_API_BASE}/zones/{zone_id}/dns_records/{record_id}"
    headers = {
        "Authorization": f"Bearer {api_token}",
        "Content-Type": "application/json"
    }
    data = {
        "type": "A",
        "name": name,
        "content": ip,
        "ttl": ttl,
        "proxied": proxied
    }

    async with httpx.AsyncClient(timeout=15) as client:
        response = await client.put(url, headers=headers, json=data)

    result = response.json()
    if not result.get("success"):
        raise RuntimeError(f"Cloudflare Fehler beim Update: {result.get('errors')}")

    return record_id


async def delete_dns_record(zone_id: str, api_token: str, record_id: str) -> bool:
    """Löscht einen DNS-Record anhand seiner ID."""
    url = f"{CF_API_BASE}/zones/{zone_id}/dns_records/{record_id}"
    headers = {"Authorization": f"Bearer {api_token}"}

    async with httpx.AsyncClient(timeout=15) as client:
        response = await client.delete(url, headers=headers)

    result = response.json()
    if not result.get("success"):
        raise RuntimeError(f"Cloudflare Fehler beim Löschen: {result.get('errors')}")

    return True


async def find_dns_record(zone_id: str, api_token: str, name: str) -> str | None:
    """Sucht einen A-Record anhand des Namens und gibt die ID zurück."""
    url = f"{CF_API_BASE}/zones/{zone_id}/dns_records"
    headers = {"Authorization": f"Bearer {api_token}"}
    params = {"type": "A", "name": name}

    async with httpx.AsyncClient(timeout=15) as client:
        response = await client.get(url, headers=headers, params=params)

    result = response.json()
    records = result.get("result", [])
    if records:
        return records[0]["id"]
    return None
