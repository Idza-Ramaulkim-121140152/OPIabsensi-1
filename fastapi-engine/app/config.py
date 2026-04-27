from functools import lru_cache

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    database_url: str = "mysql://root:@127.0.0.1:3306/face_recognition"
    similarity_threshold: float = 0.8
    arcface_model_name: str = "buffalo_l"
    face_engine_token: str = ""
    model_config = SettingsConfigDict(env_file=".env", env_file_encoding="utf-8")


@lru_cache
def get_settings() -> Settings:
    return Settings()
