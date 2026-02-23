"""
Pangolin – erstellt und löscht Tunnel-Einträge über die Pangolin API.

Pangolin (Fossorial) ist ein selbst gehostetes Reverse-Tunnel-System.
API-Doku: https://docs.fossorial.io/pangolin/api
"""

import logging
import httpx

logger = logging.getLogger(__name__)


async def create_tunnel_entry(
    base_url: str,
    api_key: str,
    domain: str,
    target_ip: str,
    target_port: int = 80,
    org_id: str = "",
    site_id: str = ""
) -> str:
    """
    Erstellt einen neuen Resource/Tunnel-Eintrag in Pangolin.
    Gibt die Ressource-ID zurück.
    
    Pangolin-Konzept:
    - Resource = eine Domain/URL die über den Tunnel erreichbar ist
    - Target    = IP:Port des echten Servers dahinter
    """
    headers = {
        "Authorization": f"Bearer {api_key}",
        "Content-Type": "application/json"
    }

    # Ressource anlegen
    resource_url = f"{base_url}/api/v1/org/{org_id}/resources"
    resource_data = {
        "name": domain,
        "subdomain": domain.split(".")[0],  # nur der erste Teil
        "siteId": site_id if site_id else None,
        "http": True,
        "ssl": True,
        "blockAccess": False
    }

    async with httpx.AsyncClient(timeout=20, verify=True) as client:
        # 1. Resource erstellen
        resp = await client.post(resource_url, headers=headers, json=resource_data)
        resp.raise_for_status()
        resource = resp.json()
        resource_id = resource.get("data", {}).get("resourceId") or resource.get("resourceId")

        if not resource_id:
            raise RuntimeError(f"Pangolin: Keine Resource-ID in Antwort: {resource}")

        logger.info(f"Pangolin Resource erstellt: {domain} (ID: {resource_id})")

        # 2. Target (Ziel-Server) zur Resource hinzufügen
        target_url = f"{base_url}/api/v1/org/{org_id}/resources/{resource_id}/targets"
        target_data = {
            "ip": target_ip,
            "port": target_port,
            "method": "http",
            "enabled": True
        }
        resp2 = await client.post(target_url, headers=headers, json=target_data)
        resp2.raise_for_status()
        logger.info(f"Pangolin Target hinzugefügt: {target_ip}:{target_port}")

    return str(resource_id)


async def delete_tunnel_entry(
    base_url: str,
    api_key: str,
    entry_id: str,
    org_id: str = ""
) -> bool:
    """Löscht einen Pangolin Resource-Eintrag."""
    headers = {
        "Authorization": f"Bearer {api_key}",
        "Content-Type": "application/json"
    }
    url = f"{base_url}/api/v1/org/{org_id}/resources/{entry_id}"

    async with httpx.AsyncClient(timeout=15, verify=True) as client:
        resp = await client.delete(url, headers=headers)
        resp.raise_for_status()

    logger.info(f"Pangolin Resource gelöscht: {entry_id}")
    return True


async def list_resources(base_url: str, api_key: str, org_id: str) -> list:
    """Listet alle vorhandenen Pangolin Resources (zum Debuggen)."""
    headers = {"Authorization": f"Bearer {api_key}"}
    url = f"{base_url}/api/v1/org/{org_id}/resources"

    async with httpx.AsyncClient(timeout=15) as client:
        resp = await client.get(url, headers=headers)
        resp.raise_for_status()

    return resp.json().get("data", [])
