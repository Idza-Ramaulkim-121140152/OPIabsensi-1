from datetime import datetime, timezone

from fastapi import Depends, FastAPI, File, Header, HTTPException, UploadFile, status

from app.config import Settings, get_settings
from app.database import Database
from app.face_service import FaceService

settings: Settings = get_settings()
database = Database(settings)
face_service = FaceService(settings)
app = FastAPI(title="Face Recognition Engine", version="1.0.0")


@app.on_event("startup")
async def on_startup() -> None:
    await database.connect()


@app.on_event("shutdown")
async def on_shutdown() -> None:
    await database.disconnect()


def verify_token(authorization: str | None = Header(default=None)) -> None:
    if settings.face_engine_token == "":
        return

    if authorization is None or not authorization.startswith("Bearer "):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Missing bearer token.",
        )

    incoming_token = authorization.split(" ", 1)[1].strip()
    if incoming_token != settings.face_engine_token:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid bearer token.",
        )


async def read_image(upload: UploadFile) -> bytes:
    if not upload.content_type or not upload.content_type.startswith("image/"):
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            detail="Uploaded file must be an image.",
        )

    image_bytes = await upload.read()
    if len(image_bytes) == 0:
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            detail="Uploaded image is empty.",
        )

    return image_bytes


@app.get("/health")
async def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/v1/register")
async def register_face(
    image: UploadFile = File(...),
    _: None = Depends(verify_token),
) -> dict[str, object]:
    image_bytes = await read_image(image)

    try:
        face_data = face_service.extract_face_data(image_bytes)
    except ValueError as exception:
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            detail=str(exception),
        ) from exception

    embedding = face_data["embedding"]
    return {
        "embedding": embedding.tolist(),
        "embedding_dimension": int(embedding.size),
        "landmarks": face_data["landmarks"],
        "image_size": face_data["image_size"],
    }


@app.post("/v1/attendance")
async def attendance(
    image: UploadFile = File(...),
    _: None = Depends(verify_token),
) -> dict[str, object]:
    image_bytes = await read_image(image)

    try:
        query_embedding = face_service.extract_embedding(image_bytes)
    except ValueError as exception:
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            detail=str(exception),
        ) from exception

    candidates = await database.fetch_embeddings()
    result = face_service.find_best_match(query_embedding, candidates)
    result["timestamp"] = datetime.now(timezone.utc).isoformat()

    return result
