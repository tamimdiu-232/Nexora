"""Authentication middleware and dependencies for Nexora AI Service."""
import secrets
from fastapi import Header, HTTPException, status
from app.config import settings


async def verify_service_token(x_service_token: str = Header(None, alias="X-Service-Token")) -> str:
    """Validate incoming request has matching X-Service-Token header using constant-time comparison."""
    if not x_service_token:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid or missing service token"
        )
    
    # Constant-time comparison prevents timing attacks
    expected = settings.SERVICE_TOKEN
    is_valid = secrets.compare_digest(
        x_service_token.strip().encode("utf-8"),
        expected.strip().encode("utf-8")
    )
    if not is_valid:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid or missing service token"
        )
    return x_service_token
