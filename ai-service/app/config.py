"""Configuration settings for Nexora AI Service."""
from typing import Optional
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        extra="ignore"
    )

    SERVICE_TOKEN: str = "development-token-secret-123456"
    DATA_DIR: str = "data"
    MAX_UPLOAD_SIZE: int = 25 * 1024 * 1024  # 25 MB
    OCR_LANGS: str = "auto"
    OCR_WORKERS: int = 3
    LLM_PROVIDER: str = "extractive"  # extractive | anthropic | openai
    LLM_API_KEY: Optional[str] = None
    LLM_MODEL: Optional[str] = None
    LLM_BASE_URL: Optional[str] = None
    EXTRACTION_MODE: str = "regex"  # regex | llm
    PORT: int = 8001


settings = Settings()
