from app.schemas import DocumentTaskResponse, OcrRequest


def run_ocr_placeholder(request: OcrRequest) -> DocumentTaskResponse:
    return DocumentTaskResponse(
        document_id=request.document_id,
        status="placeholder",
        message="OCR locale/Textract non ancora implementato.",
        data={
            "storage_path": request.storage_path,
            "driver": request.driver,
            "language": request.language,
        },
    )
