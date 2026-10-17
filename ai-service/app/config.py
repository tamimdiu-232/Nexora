from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    service_token: str = ""
    max_upload_mb: int = 25
    chunk_size: int = 800
    chunk_overlap: int = 120
    embedder: str = "hash"
    llm_provider: str = "extractive"  # extractive | anthropic | openai (any OpenAI-compatible endpoint)
    llm_api_key: str = ""
    llm_model: str = ""
    llm_base_url: str = "https://api.openai.com/v1"
    llm_timeout: int = 60
    extraction_mode: str = "rules"  # rules | llm  (llm only fills fields the rules left empty, evidence-checked)
    min_score: float = 0.12
    data_dir: str = "data"  # set empty to disable on-disk persistence of the index
    ocr_langs: str = "eng"  # e.g. "eng+ben" if tesseract Bangla data is installed


settings = Settings()
