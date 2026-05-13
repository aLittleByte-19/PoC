<?php

namespace App\Jobs;

use App\Enums\ProcessingStatus;
use App\Models\OriginalDocument;
use App\Services\DocumentProcessingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessOriginalDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 3;

    public function __construct(public OriginalDocument $document) {}

    public function handle(DocumentProcessingService $documents): void
    {
        $documents->process($this->document->refresh());
    }

    public function failed(?\Throwable $exception): void
    {
        $this->document->update(['processing_status' => ProcessingStatus::Failed]);

        Log::error('ProcessOriginalDocumentJob failed', [
            'original_id' => $this->document->id,
            'message' => $exception?->getMessage(),
        ]);
    }
}
