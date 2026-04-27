from typing import Any

import aiomysql
import json
from urllib.parse import unquote, urlparse

from app.config import Settings


class Database:
    def __init__(self, settings: Settings) -> None:
        self._settings = settings
        self._pool: aiomysql.Pool | None = None

    async def connect(self) -> None:
        mysql_cfg = self._parse_mysql_url(self._settings.database_url)
        self._pool = await aiomysql.create_pool(
            host=mysql_cfg["host"],
            port=mysql_cfg["port"],
            user=mysql_cfg["user"],
            password=mysql_cfg["password"],
            db=mysql_cfg["database"],
            minsize=1,
            maxsize=10,
            autocommit=True,
            charset="utf8mb4",
        )

    async def disconnect(self) -> None:
        if self._pool is not None:
            self._pool.close()
            await self._pool.wait_closed()

    @staticmethod
    def _parse_mysql_url(database_url: str) -> dict[str, Any]:
        parsed = urlparse(database_url)
        scheme = (parsed.scheme or "").lower()
        if scheme not in {"mysql", "mysql+aiomysql", "mariadb"}:
            raise RuntimeError(
                "DATABASE_URL harus memakai skema mysql:// atau mysql+aiomysql://."
            )

        database_name = parsed.path.lstrip("/")
        if not database_name:
            raise RuntimeError("DATABASE_URL belum berisi nama database.")

        return {
            "host": parsed.hostname or "127.0.0.1",
            "port": int(parsed.port or 3306),
            "user": unquote(parsed.username or ""),
            "password": unquote(parsed.password or ""),
            "database": database_name,
        }

    async def fetch_embeddings(self) -> list[dict[str, Any]]:
        if self._pool is None:
            raise RuntimeError("Database pool is not initialized.")

        query = """
            SELECT user_id, user_type, embedding
            FROM face_embeddings
        """
        async with self._pool.acquire() as conn:
            async with conn.cursor(aiomysql.DictCursor) as cursor:
                await cursor.execute(query)
                rows = await cursor.fetchall()

        normalized_rows: list[dict[str, Any]] = []
        for row in rows:
            item = row if isinstance(row, dict) else dict(row)
            raw_embedding = item.get("embedding")
            if isinstance(raw_embedding, (bytes, bytearray, memoryview)):
                raw_embedding = bytes(raw_embedding).decode("utf-8", errors="ignore")
            if isinstance(raw_embedding, str):
                try:
                    item["embedding"] = json.loads(raw_embedding)
                except json.JSONDecodeError:
                    item["embedding"] = []
            normalized_rows.append(item)
        return normalized_rows
