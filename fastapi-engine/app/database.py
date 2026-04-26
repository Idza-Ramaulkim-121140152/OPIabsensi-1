from typing import Any

import asyncpg
import json

from app.config import Settings


class Database:
    def __init__(self, settings: Settings) -> None:
        self._settings = settings
        self._pool: asyncpg.Pool | None = None

    async def connect(self) -> None:
        self._pool = await asyncpg.create_pool(dsn=self._settings.database_url, min_size=1, max_size=10)

    async def disconnect(self) -> None:
        if self._pool is not None:
            await self._pool.close()

    async def fetch_embeddings(self) -> list[dict[str, Any]]:
        if self._pool is None:
            raise RuntimeError("Database pool is not initialized.")

        query = """
            SELECT employee_id, embedding
            FROM face_embeddings
        """
        rows = await self._pool.fetch(query)
        normalized_rows: list[dict[str, Any]] = []
        for row in rows:
            item = dict(row)
            raw_embedding = item.get("embedding")
            if isinstance(raw_embedding, str):
                try:
                    item["embedding"] = json.loads(raw_embedding)
                except json.JSONDecodeError:
                    item["embedding"] = []
            normalized_rows.append(item)
        return normalized_rows
