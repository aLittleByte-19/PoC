from fastapi import FastAPI

from app.analysis import analyze_document_placeholder
from app.ocr import run_ocr_placeholder
from app.pdf_utils import parse_pdf_placeholder, split_pdf_placeholder
from app.schemas import (
    AnalyzeDocumentRequest,
    DocumentTaskResponse,
    OcrRequest,
    ParsePdfRequest,
    SplitDocumentRequest,
)

app = FastAPI(title="NEXUM AI Worker", version="0.1.0")


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/ocr", response_model=DocumentTaskResponse)
def ocr(request: OcrRequest) -> DocumentTaskResponse:
    return run_ocr_placeholder(request)


@app.post("/parse-pdf", response_model=DocumentTaskResponse)
def parse_pdf(request: ParsePdfRequest) -> DocumentTaskResponse:
    return parse_pdf_placeholder(request)


@app.post("/split-document", response_model=DocumentTaskResponse)
def split_document(request: SplitDocumentRequest) -> DocumentTaskResponse:
    return split_pdf_placeholder(request)


@app.post("/analyze-document", response_model=DocumentTaskResponse)
def analyze_document(request: AnalyzeDocumentRequest) -> DocumentTaskResponse:
    return analyze_document_placeholder(request)
