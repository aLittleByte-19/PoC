from app.schemas import DocumentTaskResponse, ParsePdfRequest, SplitDocumentRequest


def parse_pdf_placeholder(request: ParsePdfRequest) -> DocumentTaskResponse:
    return DocumentTaskResponse(
        document_id=request.document_id,
        status="placeholder",
        message="Parsing PDF non ancora implementato.",
        data={"storage_path": request.storage_path},
    )


def split_pdf_placeholder(request: SplitDocumentRequest) -> DocumentTaskResponse:
    return DocumentTaskResponse(
        document_id=request.document_id,
        status="placeholder",
        message="Split documentale non ancora implementato.",
        data={
            "storage_path": request.storage_path,
            "strategy": request.strategy,
        },
    )
