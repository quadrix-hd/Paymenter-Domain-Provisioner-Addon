"""
Einfache SQLite-Datenbank zum Speichern aktiver Provisions.
Wird für das spätere Aufräumen (Deprovisionierung) benötigt.
"""

import sqlite3
import json
from typing import Optional
from datetime import datetime


class Database:
    def __init__(self, db_path: str = "provisioner.db"):
        self.db_path = db_path
        self._init_db()

    def _init_db(self):
        with sqlite3.connect(self.db_path) as conn:
            conn.execute("""
                CREATE TABLE IF NOT EXISTS provisions (
                    order_id TEXT PRIMARY KEY,
                    full_domain TEXT NOT NULL,
                    server_ip TEXT NOT NULL,
                    cf_record_id TEXT,
                    pangolin_entry_id TEXT,
                    created_at TEXT DEFAULT (datetime('now')),
                    status TEXT DEFAULT 'active'
                )
            """)
            conn.commit()

    def save_provision(
        self,
        order_id: str,
        full_domain: str,
        server_ip: str,
        cf_record_id: Optional[str] = None,
        pangolin_entry_id: Optional[str] = None
    ):
        with sqlite3.connect(self.db_path) as conn:
            conn.execute("""
                INSERT OR REPLACE INTO provisions
                (order_id, full_domain, server_ip, cf_record_id, pangolin_entry_id)
                VALUES (?, ?, ?, ?, ?)
            """, (order_id, full_domain, server_ip, cf_record_id, pangolin_entry_id))
            conn.commit()

    def get_provision(self, order_id: str) -> Optional[dict]:
        with sqlite3.connect(self.db_path) as conn:
            conn.row_factory = sqlite3.Row
            row = conn.execute(
                "SELECT * FROM provisions WHERE order_id = ?", (order_id,)
            ).fetchone()
        return dict(row) if row else None

    def delete_provision(self, order_id: str):
        with sqlite3.connect(self.db_path) as conn:
            conn.execute(
                "UPDATE provisions SET status = 'deleted' WHERE order_id = ?",
                (order_id,)
            )
            conn.commit()

    def list_all(self) -> list:
        with sqlite3.connect(self.db_path) as conn:
            conn.row_factory = sqlite3.Row
            rows = conn.execute(
                "SELECT * FROM provisions WHERE status = 'active' ORDER BY created_at DESC"
            ).fetchall()
        return [dict(r) for r in rows]

    def count(self) -> int:
        with sqlite3.connect(self.db_path) as conn:
            return conn.execute(
                "SELECT COUNT(*) FROM provisions WHERE status = 'active'"
            ).fetchone()[0]
