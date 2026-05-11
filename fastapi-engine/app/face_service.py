from typing import Any

import cv2
import numpy as np
from insightface.app import FaceAnalysis

from app.config import Settings


class FaceService:
    def __init__(self, settings: Settings) -> None:
        self._settings = settings
        self._engine = FaceAnalysis(name=settings.arcface_model_name, providers=["CPUExecutionProvider"])
        self._engine.prepare(ctx_id=0, det_size=(640, 640))

    def extract_face_data(self, image_bytes: bytes) -> dict[str, Any]:
        image_array = np.frombuffer(image_bytes, dtype=np.uint8)
        image = cv2.imdecode(image_array, cv2.IMREAD_COLOR)

        if image is None:
            raise ValueError("Image could not be decoded.")

        faces = self._engine.get(image)
        if not faces:
            raise ValueError("No face detected.")

        # Pick the largest face for consistency in multi-face scenes.
        face = max(faces, key=lambda item: (item.bbox[2] - item.bbox[0]) * (item.bbox[3] - item.bbox[1]))
        embedding = np.asarray(face.normed_embedding, dtype=np.float32)

        if embedding.size != 512:
            raise ValueError("ArcFace embedding is not 512-dim.")

        raw_landmarks = getattr(face, "kps", None)
        landmarks: list[dict[str, float]] = []
        if raw_landmarks is not None:
            points = np.asarray(raw_landmarks, dtype=np.float32).reshape(-1, 2)
            landmarks = [
                {"x": round(float(point[0]), 4), "y": round(float(point[1]), 4)}
                for point in points
            ]

        image_height, image_width = image.shape[:2]

        return {
            "embedding": embedding,
            "landmarks": landmarks,
            "image_size": {
                "width": int(image_width),
                "height": int(image_height),
            },
        }

    def extract_embedding(self, image_bytes: bytes) -> np.ndarray:
        return self.extract_face_data(image_bytes)["embedding"]

    def find_best_match(self, query_embedding: np.ndarray, candidates: list[dict[str, Any]]) -> dict[str, Any]:
        best_user_id: int | None = None
        best_user_type: str | None = None
        best_score = -1.0

        for candidate in candidates:
            raw_embedding = candidate.get("embedding")
            if not isinstance(raw_embedding, list) or len(raw_embedding) == 0:
                continue

            candidate_embedding = np.asarray(raw_embedding, dtype=np.float32)
            if candidate_embedding.shape != query_embedding.shape:
                continue

            score = self._cosine_similarity(query_embedding, candidate_embedding)
            if score > best_score:
                best_score = score
                best_user_id = int(candidate["user_id"])
                best_user_type = str(candidate["user_type"])

        if best_user_id is None:
            return {
                "status": "unknown",
                "user_id": None,
                "user_type": None,
                "confidence": 0.0,
            }

        status = "matched" if best_score >= self._settings.similarity_threshold else "unknown"
        if status == "unknown":
            best_user_id = None
            best_user_type = None

        return {
            "status": status,
            "user_id": best_user_id,
            "user_type": best_user_type,
            "confidence": round(float(best_score), 6),
        }

    @staticmethod
    def _cosine_similarity(a: np.ndarray, b: np.ndarray) -> float:
        denominator = (np.linalg.norm(a) * np.linalg.norm(b)) + 1e-12
        return float(np.dot(a, b) / denominator)
