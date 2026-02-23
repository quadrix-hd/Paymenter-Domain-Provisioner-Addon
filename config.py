"""
Konfiguration – alle Einstellungen werden aus Umgebungsvariablen oder .env gelesen.
"""

from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    # ── Paymenter ──────────────────────────────────────────────────────────────
    WEBHOOK_SECRET: str = ""          # Paymenter Webhook Secret (empfohlen!)

    # ── Basis-Domain ───────────────────────────────────────────────────────────
    BASE_DOMAIN: str = "example.com"  # z.B. "meinedomain.de"

    # ── Cloudflare ─────────────────────────────────────────────────────────────
    CF_API_TOKEN: str = ""            # Cloudflare API Token (DNS:Edit Berechtigung)
    CF_ZONE_ID: str = ""              # Zone-ID deiner Domain in Cloudflare
    CF_PROXIED: bool = False          # True = Cloudflare Proxy (orange Cloud), False = nur DNS

    # ── Pangolin ───────────────────────────────────────────────────────────────
    PANGOLIN_URL: str = ""            # z.B. "https://pangolin.meinedomain.de"
    PANGOLIN_API_KEY: str = ""        # Pangolin API Key
    PANGOLIN_ORG_ID: str = ""         # Pangolin Organisation-ID
    PANGOLIN_SITE_ID: str = ""        # Pangolin Site-ID (optional, je nach Setup)

    # ── Standard Ziel-Port ─────────────────────────────────────────────────────
    DEFAULT_TARGET_PORT: int = 80     # Ziel-Port auf dem Kundenserver

    class Config:
        env_file = ".env"
        env_file_encoding = "utf-8"
