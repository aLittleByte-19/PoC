from app.schemas import AnalyzeDocumentRequest, DocumentTaskResponse


def analyze_document_placeholder(request: AnalyzeDocumentRequest) -> DocumentTaskResponse:
    return DocumentTaskResponse(
        document_id=request.document_id,
        status="placeholder",
        message="Analisi documentale PoC non ancora implementata.",
        data={
            "storage_path": request.storage_path,
            "manual_classification": request.manual_classification,
            "manual_metadata": request.manual_metadata,
            "result": {
                "document_type": request.manual_classification or "cedolino",
                "employee_name": "Non disponibile",
                "company": request.manual_metadata.get("company", "Non disponibile"),
                "document_date": request.manual_metadata.get("document_date"),
                "page_count": None,
                "description": "Risultato simulato per PoC.",
                "confidence": 0.8,
                "recipient": "Non disponibile",
                "split_status": "placeholder",
            },
        },
    )
