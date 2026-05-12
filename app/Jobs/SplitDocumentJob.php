<?php

namespace App\Jobs;

use App\Enums\ProcessingStatus;
use App\Models\OriginalDocument;
use App\Services\DocumentProcessingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SplitDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(public OriginalDocument $document) {}

    public function handle(DocumentProcessingService $documents): void
    {
        $this->document->update(['processing_status' => ProcessingStatus::Processing]);

        try {
            $subDocuments = $documents->splitIntoSubDocuments($this->document);

            foreach ($subDocuments as $subDocument) {
                ExtractSubDocumentJob::dispatch($subDocument);
            }

            $this->document->update(['processing_status' => ProcessingStatus::Completed]);
        } catch (\Throwable $e) {
            Log::error('SplitDocumentJob failed', [
                'original_id' => $this->document->id,
                'message' => $e->getMessage(),
            ]);
            $this->document->update(['processing_status' => ProcessingStatus::Failed]);
            throw $e;
        }
    }
}
