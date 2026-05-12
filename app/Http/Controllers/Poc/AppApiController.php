<?php

namespace App\Http\Controllers\Poc;

use App\Enums\CommunicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Communication;
use App\Models\ExtractedData;
use App\Models\OriginalDocument;
use App\Models\SubDocument;
use App\Services\BedrockService;
use App\Services\DocumentProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AppApiController extends Controller
{
    private const TONES = [
        'Chiaro e diretto',
        'Più istituzionale',
        'Più sintetico',
        'Empatico',
        'Tecnico',
    ];

    private const STYLES = [
        'Testo informativo',
        'Avviso operativo',
        'Aggiornamento breve',
    ];

    public function state(): JsonResponse
    {
        return response()->json($this->stateData());
    }

    public function generateCommunication(Request $request, BedrockService $bedrock): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'min:12', 'max:5000'],
            'tone' => ['required', 'string', Rule::in(self::TONES)],
            'style' => ['required', 'string', Rule::in(self::STYLES)],
        ]);

        try {
            $generated = $bedrock->generateCommunication(
                $validated['prompt'],
                $validated['tone'],
                $validated['style'],
            );
        } catch (\Throwable $e) {
            Log::warning('PoC communication generation failed', ['message' => $e->getMessage()]);

            return response()->json([
                'message' => 'Generazione non disponibile. Verifica la configurazione AI e riprova.',
            ], 502);
        }

        $communication = Communication::create([
            'prompt' => $validated['prompt'],
            'tone' => $validated['tone'],
            'style' => $validated['style'],
            'generated_title' => $generated['title'],
            'generated_body' => $generated['body'],
            'status' => CommunicationStatus::Draft,
        ]);

        return response()->json([
            'message' => 'Bozza generata correttamente.',
            'communication' => $this->serializeCommunication($communication),
            'state' => $this->stateData(),
        ], 201);
    }

    public function runDocumentOcr(Request $request, DocumentProcessingService $documents): JsonResponse
    {
        $validated = $request->validate([
            'document' => ['required', 'file', 'mimetypes:application/pdf', 'max:10240'],
        ]);

        try {
            $original = $documents->handleUpload($validated['document']);
        } catch (\Throwable $e) {
            Log::warning('PoC document processing failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Analisi documento non disponibile. Verifica il PDF o la configurazione AI e riprova.',
            ], 502);
        }

        $subDocuments = $original->subDocuments()
            ->with(['originalDocument', 'extractedData'])
            ->latest()
            ->get()
            ->map(fn (SubDocument $document): array => $this->serializeDocument($document))
            ->values()
            ->all();

        return response()->json([
            'message' => 'Documento analizzato: split iniziale e campi OCR disponibili nella sezione risultati.',
            'document' => $subDocuments[0] ?? null,
            'documents' => $subDocuments,
            'state' => $this->stateData(),
        ], 201);
    }

    public function previewSubDocument(SubDocument $subDocument)
    {
        abort_unless(Storage::disk('local')->exists($subDocument->file_path), 404);

        $filename = $subDocument->originalDocument?->original_filename ?: 'documento.pdf';

        return response()->file(Storage::disk('local')->path($subDocument->file_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $filename).'"',
        ]);
    }

    private function stateData(): array
    {
        $communications = Communication::query()->latest()->limit(6)->get();
        $documents = SubDocument::query()
            ->with(['originalDocument', 'extractedData'])
            ->latest()
            ->limit(40)
            ->get();

        $documentCount = OriginalDocument::query()->count();
        $withConfidenceCount = ExtractedData::query()
            ->whereNotNull('confidence_score')
            ->count();
        $reviewCount = ExtractedData::query()
            ->where(function ($query) {
                $query->whereNull('confidence_score')
                    ->orWhere('confidence_score', '<', (int) env('POC_CONFIDENCE_THRESHOLD', 80));
            })
            ->count();

        return [
            'assistant' => [
                'metrics' => [
                    ['value' => Communication::query()->count(), 'label' => 'Contenuti generati'],
                    ['value' => Communication::query()->where('status', CommunicationStatus::Draft)->count(), 'label' => 'Bozze generate'],
                ],
                'history' => $communications
                    ->map(fn (Communication $communication): array => $this->serializeCommunication($communication))
                    ->values()
                    ->all(),
            ],
            'copilot' => [
                'metrics' => [
                    ['value' => $documentCount, 'label' => 'Documenti analizzati'],
                    ['value' => $documents->count(), 'label' => 'Sotto-documenti rilevati'],
                    ['value' => $withConfidenceCount, 'label' => 'Campi con confidenza'],
                    ['value' => $reviewCount, 'label' => 'Da verificare'],
                ],
                'documents' => $documents
                    ->map(fn (SubDocument $document): array => $this->serializeDocument($document))
                    ->values()
                    ->all(),
            ],
        ];
    }

    private function serializeCommunication(Communication $communication): array
    {
        return [
            'id' => $communication->id,
            'prompt' => $communication->prompt,
            'tone' => $communication->tone,
            'style' => $communication->style,
            'title' => $communication->generated_title,
            'body' => $communication->generated_body,
            'status' => $communication->status->label(),
            'createdAt' => $communication->created_at?->format('d/m/Y H:i'),
        ];
    }

    private function serializeDocument(SubDocument $subDocument): array
    {
        $original = $subDocument->originalDocument;
        $data = $subDocument->extractedData;
        $employee = trim(implode(' ', array_filter([
            $data?->employee_first_name,
            $data?->employee_last_name,
        ])));
        $confidence = $data?->confidence_score;
        $pages = max(1, ((int) $subDocument->end_page - (int) $subDocument->start_page) + 1);

        return [
            'id' => 'sub-'.$subDocument->id,
            'title' => $data?->document_type ?: $original?->original_filename,
            'employee' => $employee !== '' ? $employee : null,
            'company' => $data?->company_name,
            'file' => $original?->original_filename,
            'date' => $data?->document_date?->format('d/m/Y'),
            'pages' => $pages,
            'type' => $data?->document_type,
            'description' => $data?->description,
            'confidence' => $confidence,
            'previewUrl' => route('poc.documents.preview', ['subDocument' => $subDocument->id]),
            'previewLines' => [
                'Split iniziale: pagine '.$subDocument->start_page.'-'.$subDocument->end_page.'.',
                'File originale: '.($original?->original_filename ?: 'Non disponibile').'.',
                'Campi OCR rilevati dal servizio AI configurato o dal fallback PoC.',
            ],
        ];
    }
}
