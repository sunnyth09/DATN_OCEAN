"""
Face Verification Service — Core Utility Module
Sử dụng face_recognition (dlib) để encode và verify khuôn mặt.
Accuracy: 99.38% trên LFW benchmark (dlib CNN model).
"""

import io
import base64
import logging
import numpy as np
import face_recognition
from PIL import Image

logger = logging.getLogger("face_utils")

# Giới hạn kích thước ảnh input (5MB)
MAX_IMAGE_SIZE = 5 * 1024 * 1024


def decode_base64_image(base64_string: str) -> np.ndarray:
    """
    Decode ảnh base64 thành numpy array (RGB) cho face_recognition.
    Hỗ trợ cả format có header (data:image/jpeg;base64,...) và không header.
    
    Raises:
        ValueError: Nếu ảnh không hợp lệ hoặc quá lớn.
    """
    # Strip header nếu có
    if ";base64," in base64_string:
        base64_string = base64_string.split(";base64,")[1]

    image_bytes = base64.b64decode(base64_string)

    if len(image_bytes) > MAX_IMAGE_SIZE:
        raise ValueError(f"Image too large: {len(image_bytes)} bytes (max {MAX_IMAGE_SIZE})")

    # Validate magic bytes (JPEG, PNG)
    if not (image_bytes[:2] == b'\xff\xd8' or image_bytes[:4] == b'\x89PNG'):
        raise ValueError("Invalid image format. Only JPEG and PNG are supported.")

    image = Image.open(io.BytesIO(image_bytes))
    # Convert to RGB (face_recognition yêu cầu RGB)
    image = image.convert("RGB")

    # Resize nếu ảnh quá lớn (tăng tốc xử lý)
    max_dim = 1024
    if max(image.size) > max_dim:
        ratio = max_dim / max(image.size)
        new_size = (int(image.size[0] * ratio), int(image.size[1] * ratio))
        image = image.resize(new_size, Image.LANCZOS)

    return np.array(image)


def encode_face(image_array: np.ndarray) -> list[float] | None:
    """
    Encode khuôn mặt trong ảnh thành 128-dim vector.
    
    Returns:
        list[float]: 128-dim encoding vector, hoặc None nếu không tìm thấy khuôn mặt.
    """
    # Detect face locations trước
    face_locations = face_recognition.face_locations(image_array, model="hog")

    if not face_locations:
        return None

    # Lấy encoding của khuôn mặt đầu tiên (lớn nhất)
    if len(face_locations) > 1:
        # Chọn khuôn mặt lớn nhất
        face_locations = [max(face_locations, key=lambda loc: (loc[2] - loc[0]) * (loc[1] - loc[3]))]

    encodings = face_recognition.face_encodings(image_array, known_face_locations=face_locations)

    if not encodings:
        return None

    return encodings[0].tolist()


def verify_face(
    image_encoding: list[float],
    registered_encodings: list[list[float]],
    tolerance: float = 0.45,
) -> dict:
    """
    So sánh encoding mới với danh sách encodings đã đăng ký.
    
    Args:
        image_encoding: 128-dim encoding vector của ảnh cần verify
        registered_encodings: Danh sách encodings đã đăng ký
        tolerance: Ngưỡng distance cho phép (mặc định 0.45, nghiêm ngặt)
    
    Returns:
        dict với keys: match (bool), distance (float), confidence (float)
    """
    if not registered_encodings:
        return {"match": False, "distance": 1.0, "confidence": 0.0, "reason": "no_registered_faces"}

    new_encoding = np.array(image_encoding)
    registered = [np.array(enc) for enc in registered_encodings]

    # Tính euclidean distance với tất cả encodings đã đăng ký
    distances = face_recognition.face_distance(registered, new_encoding)

    # Lấy distance nhỏ nhất (match tốt nhất)
    min_distance = float(np.min(distances))
    best_match_idx = int(np.argmin(distances))

    # Confidence: chuyển distance → confidence (0-1)
    # distance = 0 → confidence = 1.0 (perfect match)
    # distance = tolerance → confidence ≈ 0.5
    # distance > tolerance → confidence < 0.5
    confidence = max(0.0, min(1.0, 1.0 - (min_distance / (tolerance * 2))))

    is_match = min_distance <= tolerance

    return {
        "match": is_match,
        "distance": round(min_distance, 4),
        "confidence": round(confidence, 4),
        "best_match_index": best_match_idx,
        "all_distances": [round(float(d), 4) for d in distances],
    }
