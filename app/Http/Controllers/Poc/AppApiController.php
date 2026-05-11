<?php

namespace App\Http\Controllers\Poc;

use App\Enums\CommunicationStatus;
use App\Enums\ProcessingStatus;
use App\Filament\Resources\UserResource;
use App\Http\Controllers\Controller;
use App\Models\Communication;
use App\Models\ExtractedData;
use App\Models\OriginalDocument;
use App\Models\SubDocument;
use App\Services\BedrockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

    private const CHANNELS = [
        'Email interna',
        'News portale',
        'Notifica rapida',
    ];

    private const AUDIENCES = [
        'Tutti i dipendenti',
        'Manager e responsabili',
        'Team HR',
    ];

    public function session(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'csrfToken' => csrf_token(),
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'initials' => $this->initials($user->name),
                'isAdmin' => $user->is_admin,
            ],
            'links' => [
                'users' => $user->is_admin ? UserResource::getUrl('index') : null,
                'logout' => route('poc.logout'),
            ],
        ]);
    }

    public function state(): JsonResponse
    {
        return response()->json($this->stateData());
    }

    public function generateCommunication(Request $request, BedrockService $bedrock): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'min:12', 'max:5000'],
            'audience' => ['required', 'string', Rule::in(self::AUDIENCES)],
            'tone' => ['required', 'string', Rule::in(self::TONES)],
            'style' => ['required', 'string', Rule::in(self::STYLES)],
            'channel' => ['required', 'string', Rule::in(self::CHANNELS)],
        ]);

        $style = $validated['style'].' / '.$validated['channel'];
        $prompt = implode("\n", [
            'Pubblico: '.$validated['audience'],
            'Canale: '.$validated['channel'],
            'Richiesta: '.$validated['prompt'],
        ]);

        try {
            $generated = $bedrock->generateCommunication($prompt, $validated['tone'], $style);
        } catch (\Throwable $e) {
            Log::warning('PoC communication generation failed', ['message' => $e->getMessage()]);

            return response()->json([
                'message' => 'Generazione non disponibile. Verifica la configurazione AI e riprova.',
            ], 502);
        }

        $communication = Communication::create([
            'prompt' => $validated['prompt'],
            'tone' => $validated['tone'],
            'style' => $style,
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

    public function runDocumentOcr(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document' => ['required', 'file', 'mimetypes:application/pdf', 'max:10240'],
        ]);

        $file = $validated['document'];
        $path = $file->store('documents/originals', 'local');

        $original = OriginalDocument::create([
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'processing_status' => ProcessingStatus::Processing,
        ]);

        $workerMessage = 'OCR locale/Textract predisposto; campi non ancora disponibili.';

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->post(rtrim((string) config('services.ai_worker.url'), '/').'/ocr', [
                    'document_id' => (string) $original->id,
                    'storage_path' => $path,
                    'driver' => config('services.textract.enabled') ? 'textract' : 'local',
                    'language' => 'ita+eng',
                ]);

            if ($response->successful()) {
                $workerMessage = $response->json('message') ?: $workerMessage;
            }
        } catch (\Throwable $e) {
            Log::info('PoC OCR worker unavailable; keeping null extracted fields', [
                'original_document_id' => $original->id,
                'message' => $e->getMessage(),
            ]);
        }

        $subDocument = SubDocument::create([
            'original_document_id' => $original->id,
            'file_path' => $path,
            'start_page' => 1,
            'end_page' => 1,
        ]);

        ExtractedData::create([
            'sub_document_id' => $subDocument->id,
            'employee_first_name' => null,
            'employee_last_name' => null,
            'company_name' => null,
            'document_date' => null,
            'document_type' => null,
            'description' => null,
            'confidence_score' => null,
        ]);

        $original->update(['processing_status' => ProcessingStatus::Completed]);

        return response()->json([
            'message' => $workerMessage,
            'document' => $this->serializeDocument($subDocument->fresh(['originalDocument', 'extractedData'])),
            'state' => $this->stateData(),
        ], 201);
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
        $sentCount = SubDocument::query()->where('send_status', 'sent')->count();
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
                    ['value' => Communication::query()->where('status', CommunicationStatus::Draft)->count(), 'label' => 'Bozze da rivedere'],
                    ['value' => 0, 'label' => 'Feedback raccolti'],
                    ['value' => 'n/d', 'label' => 'Rating medio'],
                ],
                'history' => $communications
                    ->map(fn (Communication $communication): array => $this->serializeCommunication($communication))
                    ->values()
                    ->all(),
            ],
            'copilot' => [
                'metrics' => [
                    ['value' => $documentCount, 'label' => 'Documenti analizzati'],
                    ['value' => $reviewCount, 'label' => 'Da verificare'],
                    ['value' => max(0, $documents->count() - $reviewCount - $sentCount), 'label' => 'Pronti per invio'],
                    ['value' => $sentCount, 'label' => 'Inviati'],
                    ['value' => 'n/d', 'label' => 'Tempo medio analisi'],
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
            'deliveryStatus' => $subDocument->send_status->label(),
            'previewLines' => [
                'Anteprima PDF non disponibile nella PoC.',
                'OCR locale/Textract predisposto.',
                'Campi mostrati come non disponibili finché non vengono estratti.',
            ],
        ];
    }

    private function initials(string $name): string
    {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));

        if ($parts === []) {
            return 'U';
        }

        return strtoupper(substr($parts[0], 0, 1).substr($parts[1] ?? '', 0, 1));
    }
}
