"""
Face Verification Microservice — FastAPI Server
Endpoints:
  POST /encode   — Nhận ảnh base64, trả về 128-dim face encoding
  POST /verify   — Nhận ảnh + encodings đã đăng ký, trả về match result
  GET  /health   — Health check
"""

import os
import logging
import fastapi
from fastapi import FastAPI, HTTPException, Request
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, field_validator
from face_utils import decode_base64_image, encode_face, verify_face

# Logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("face-service")

# Config from environment
FACE_TOLERANCE = float(os.environ.get("FACE_TOLERANCE", "0.45"))
MAX_ENCODINGS_PER_REQUEST = int(os.environ.get("MAX_ENCODINGS_PER_REQUEST", "10"))
INTERNAL_SECRET = os.environ.get("FACE_SERVICE_SECRET", "super-secret-default-key-change-in-prod")

app = FastAPI(
    title="Face Verification Service",
    description="Internal microservice cho xác thực khuôn mặt chấm công",
    version="1.0.0",
    docs_url=None,   # Disable Swagger UI trong production
    redoc_url=None,  # Disable ReDoc trong production
)

@app.middleware("http")
async def verify_internal_secret(request: Request, call_next):
    # Cho phép health check không cần auth
    if request.url.path == "/health":
        return await call_next(request)
        
    secret = request.headers.get("X-Internal-Secret")
    if secret != INTERNAL_SECRET:
        # Lỗi Unauthorized nếu header bị thiếu hoặc sai
        return fastapi.responses.JSONResponse(
            status_code=401,
            content={"detail": "Unauthorized: Invalid or missing X-Internal-Secret"}
        )
    return await call_next(request)

# CORS — chỉ cho phép internal network
# TODO(security): Trong production, restrict origins cụ thể thay vì wildcard
allowed_origins = os.environ.get("ALLOWED_ORIGINS", "").split(",")
if allowed_origins == [""]:
    allowed_origins = []

app.add_middleware(
    CORSMiddleware,
    allow_origins=allowed_origins,
    allow_methods=["POST", "GET"],
    allow_headers=["Content-Type", "Authorization"],
)


# ── Request/Response Models ──

class EncodeRequest(BaseModel):
    """Request body cho endpoint /encode"""
    image: str  # base64 encoded image

    @field_validator("image")
    @classmethod
    def validate_image(cls, v: str) -> str:
        if not v or len(v) < 100:
            raise ValueError("Image data is too short or empty")
        # Max ~7MB base64 string (≈5MB raw image)
        if len(v) > 7 * 1024 * 1024:
            raise ValueError("Image data exceeds maximum size")
        return v


class EncodeResponse(BaseModel):
    success: bool
    encoding: list[float] | None = None
    message: str = ""


class VerifyRequest(BaseModel):
    """Request body cho endpoint /verify"""
    image: str                           # base64 encoded image (ảnh cần verify)
    registered_encodings: list[list[float]]  # Danh sách encodings đã đăng ký
    tolerance: float | None = None       # Override tolerance (optional)

    @field_validator("image")
    @classmethod
    def validate_image(cls, v: str) -> str:
        if not v or len(v) < 100:
            raise ValueError("Image data is too short or empty")
        if len(v) > 7 * 1024 * 1024:
            raise ValueError("Image data exceeds maximum size")
        return v

    @field_validator("registered_encodings")
    @classmethod
    def validate_encodings(cls, v: list) -> list:
        if not v:
            raise ValueError("At least one registered encoding is required")
        if len(v) > MAX_ENCODINGS_PER_REQUEST:
            raise ValueError(f"Too many encodings (max {MAX_ENCODINGS_PER_REQUEST})")
        for enc in v:
            if len(enc) != 128:
                raise ValueError("Each encoding must have exactly 128 dimensions")
        return v


class VerifyResponse(BaseModel):
    success: bool
    match: bool = False
    distance: float = 1.0
    confidence: float = 0.0
    message: str = ""


# ── Endpoints ──

@app.get("/health")
async def health_check():
    """Health check — dùng cho Docker healthcheck và monitoring."""
    return {"status": "ok", "service": "face-verification", "tolerance": FACE_TOLERANCE}


@app.post("/encode", response_model=EncodeResponse)
async def encode_endpoint(request: EncodeRequest):
    """
    Nhận ảnh base64, detect khuôn mặt và trả về 128-dim encoding vector.
    Dùng khi nhân viên đăng ký khuôn mặt.
    """
    try:
        image_array = decode_base64_image(request.image)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=f"Invalid image: {e}")
    except Exception:
        raise HTTPException(status_code=400, detail="Failed to decode image")

    encoding = encode_face(image_array)

    if encoding is None:
        return EncodeResponse(
            success=False,
            encoding=None,
            message="Không phát hiện khuôn mặt trong ảnh. Vui lòng chụp lại với ánh sáng tốt hơn.",
        )

    logger.info("Face encoded successfully (128-dim vector)")
    return EncodeResponse(
        success=True,
        encoding=encoding,
        message="Encoding thành công.",
    )


@app.post("/verify", response_model=VerifyResponse)
async def verify_endpoint(request: VerifyRequest):
    """
    Nhận ảnh mới + danh sách encodings đã đăng ký.
    So sánh và trả về kết quả match/không match.
    Dùng khi nhân viên chấm công.
    """
    try:
        image_array = decode_base64_image(request.image)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=f"Invalid image: {e}")
    except Exception:
        raise HTTPException(status_code=400, detail="Failed to decode image")

    # Encode ảnh mới
    new_encoding = encode_face(image_array)

    if new_encoding is None:
        return VerifyResponse(
            success=False,
            match=False,
            distance=1.0,
            confidence=0.0,
            message="Không phát hiện khuôn mặt trong ảnh chấm công.",
        )

    # So sánh với encodings đã đăng ký
    tolerance = request.tolerance if request.tolerance is not None else FACE_TOLERANCE
    result = verify_face(new_encoding, request.registered_encodings, tolerance=tolerance)

    logger.info(
        "Face verification: match=%s, distance=%.4f, confidence=%.4f",
        result["match"], result["distance"], result["confidence"],
    )

    return VerifyResponse(
        success=True,
        match=result["match"],
        distance=result["distance"],
        confidence=result["confidence"],
        message="Xác thực thành công!" if result["match"] else "Khuôn mặt không khớp.",
    )


if __name__ == "__main__":
    import uvicorn
    # TODO(security): Trong production, bind 127.0.0.1 thay vì 0.0.0.0
    # Hiện tại dùng 0.0.0.0 để Docker container có thể nhận request từ host
    uvicorn.run(app, host="0.0.0.0", port=8001)
