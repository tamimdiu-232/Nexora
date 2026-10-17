import hmac
from fastapi import Header, HTTPException
from .config import settings


def require_service_token(x_service_token: str = Header(default="")):
    if not settings.service_token or not hmac.compare_digest(x_service_token, settings.service_token):
        raise HTTPException(status_code=401, detail="Invalid service credentials")
