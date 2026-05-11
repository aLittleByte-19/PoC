from typing import Any, Literal

from pydantic import BaseModel, Field


class BaseDocumentTask(BaseModel):
    document_id: str = Field(..., description="Identificativo applicativo del documento.")
    storage_path: str = Field(..., description="Percorso del file nello storage S3-compatible.")


class OcrRequest(BaseDocumentTask):
    driver: Literal["local", "textract"] = "local"
    language: str = "ita+eng"


class ParsePdfRequest(BaseDocumentTask):
    extract_text: bool = True


class SplitDocumentRequest(BaseDocumentTask):
    strategy: Literal["none", "page", "recipient", "classifier"] = "none"


class AnalyzeDocumentRequest(BaseDocumentTask):
    manual_classification: str | None = None
    manual_metadata: dict[str, Any] = Field(default_factory=dict)
    run_ocr: bool = True
    run_split: bool = True


class DocumentTaskResponse(BaseModel):
    document_id: str
    status: Literal["placeholder", "queued", "completed", "failed"]
    message: str
    data: dict[str, Any] = Field(default_factory=dict)
