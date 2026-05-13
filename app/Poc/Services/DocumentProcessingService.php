<?php

namespace App\Poc\Services;

use App\Poc\Enums\ProcessingStatus;
use App\Poc\Models\ExtractedData;
use App\Poc\Models\OriginalDocument;
use App\Poc\Models\SubDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;

class DocumentProcessingService
{
    public function __construct(private readonly BedrockService $bedrock) {}

    /**
     * @throws \RuntimeException when the upload cannot be persisted to the configured disk.
     */
    public function storeUpload(UploadedFile $file): OriginalDocument
    {
        $path = $file->store('documents/originals', $this->documentDisk());

        if (! is_string($path) || $path === '') {
            throw new \RuntimeException('Impossibile salvare il documento nello storage configurato.');
        }

        $safeName = preg_replace('/[^\w.\-]/u', '_', $file->getClientOriginalName()) ?: 'documento.pdf';

        return $this->handleStoredFile($path, $safeName);
    }

    public function handleUpload(UploadedFile $file): OriginalDocument
    {
        $original = $this->storeUpload($file);
        $this->process($original);

        return $original;
    }

    public function handleStoredFile(string $path, string $filename): OriginalDocument
    {
        return OriginalDocument::create([
            'file_path' => $path,
            'original_filename' => $filename,
            'processing_status' => ProcessingStatus::Pending,
        ]);
    }

    public function extractAndSaveFields(SubDocument $subDocument): void
    {
        try {
            $fields = $this->extractFields($subDocument->file_path);
            ExtractedData::create(array_merge(
                ['sub_document_id' => $subDocument->id],
                $fields,
            ));
        } catch (\Throwable $e) {
            Log::error('DocumentProcessingService: extraction failed', [
                'sub_document_id' => $subDocument->id,
                'message' => $e->getMessage(),
            ]);
            $this->createEmptyExtractedData($subDocument);
        }
    }

    /**
     * Process an upload end-to-end while keeping file cleanup aligned with DB commits.
     */
    public function process(OriginalDocument $original): void
    {
        $original->update(['processing_status' => ProcessingStatus::Processing]);

        $createdSplitPaths = [];
        $committed = false;

        try {
            $segments = $this->analyzeDocumentStructure($original);
            $preparedSegments = [];

            // Prepare files before mutating records so a failed transaction leaves existing splits intact.
            foreach ($segments as $segment) {
                $preparedSegment = $this->prepareSplitSegment($original, $segment);
                $createdSplitPaths[] = $preparedSegment['file_path'];
                $preparedSegments[] = $preparedSegment;
            }

            $oldSplitPaths = [];

            DB::transaction(function () use ($preparedSegments, $original, &$oldSplitPaths): void {
                $oldSplitPaths = $this->deleteExistingSplitRecords($original);

                foreach ($preparedSegments as $segment) {
                    $subDocument = $this->createSubDocumentFromPreparedSegment($original, $segment);
                    $this->runDataExtraction($subDocument);
                }
            });

            $committed = true;
            $this->deleteStoragePaths($oldSplitPaths);

            $original->update(['processing_status' => ProcessingStatus::Completed]);
        } catch (\Throwable $e) {
            if (! $committed) {
                $this->deleteStoragePaths($createdSplitPaths);
            }

            $this->handleProcessingFailure($original, $e);
        }
    }

    /**
     * @return array<int, array{employee_name: string, start_page: int, end_page: int}>
     */
    private function analyzeDocumentStructure(OriginalDocument $original): array
    {
        return $this->normalizeSegments(
            $this->splitDocument($original->file_path),
            $original->file_path
        );
    }

    /**
     * Create the split PDF for a segment before any database mutation.
     *
     * @param  array{employee_name: string, start_page: int, end_page: int}  $segment
     * @return array{employee_name: string, start_page: int, end_page: int, file_path: string}
     */
    private function prepareSplitSegment(OriginalDocument $original, array $segment): array
    {
        $splitPath = $this->extractPages(
            $original->file_path,
            $original->id,
            $segment['employee_name'],
            (int) $segment['start_page'],
            (int) $segment['end_page']
        );

        return array_merge($segment, ['file_path' => $splitPath]);
    }

    /**
     * @param  array{employee_name: string, start_page: int, end_page: int, file_path: string}  $segment
     */
    private function createSubDocumentFromPreparedSegment(OriginalDocument $original, array $segment): SubDocument
    {
        return SubDocument::create([
            'original_document_id' => $original->id,
            'file_path' => $segment['file_path'],
            'start_page' => $segment['start_page'],
            'end_page' => $segment['end_page'],
        ]);
    }

    private function runDataExtraction(SubDocument $subDocument): void
    {
        try {
            $fields = $this->extractFields($subDocument->file_path);

            ExtractedData::create(array_merge(
                ['sub_document_id' => $subDocument->id],
                $fields
            ));
        } catch (\Throwable $e) {
            Log::warning("Extraction failed for split {$subDocument->id}", ['error' => $e->getMessage()]);
            $this->createEmptyExtractedData($subDocument);
        }
    }

    /**
     * Mark the document as failed, then rethrow so the queue can apply its retry policy.
     *
     * @throws \Throwable
     */
    private function handleProcessingFailure(OriginalDocument $original, \Throwable $e): void
    {
        Log::error('PDF Pipeline Failure', [
            'document_id' => $original->id,
            'error' => $e->getMessage(),
        ]);

        $original->update(['processing_status' => ProcessingStatus::Failed]);

        throw $e;
    }

    /**
     * Delete existing sub-document records and return storage paths for cleanup after commit.
     *
     * @return array<int, string>
     */
    private function deleteExistingSplitRecords(OriginalDocument $original): array
    {
        $splits = $original->subDocuments()->get(['id', 'file_path']);
        $paths = $splits->pluck('file_path')->filter()->values()->all();

        $splits->each(function (SubDocument $split): void {
            $split->delete();
        });

        return $paths;
    }

    /**
     * Delete split PDFs from storage without failing an otherwise completed DB update.
     *
     * @param  array<int, string>  $paths
     */
    private function deleteStoragePaths(array $paths): void
    {
        foreach (array_unique(array_filter($paths)) as $path) {
            try {
                Storage::disk($this->documentDisk())->delete($path);
            } catch (\Throwable $e) {
                Log::warning('DocumentProcessingService: storage cleanup failed', [
                    'path' => $path,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Keep the PoC useful even when the split model cannot identify multiple recipients:
     * one fallback segment still allows field extraction on the uploaded PDF.
     *
     * @param  array<int, array{employee_name?: string, start_page?: int, end_page?: int}>  $segments
     * @return array<int, array{employee_name: string, start_page: int, end_page: int}>
     */
    private function normalizeSegments(array $segments, string $sourcePath): array
    {
        $pageCount = $this->pageCount($sourcePath);

        if ($segments === []) {
            return [[
                'employee_name' => 'documento',
                'start_page' => 1,
                'end_page' => $pageCount,
            ]];
        }

        return array_values(array_map(function (array $segment) use ($pageCount): array {
            $startPage = max(1, (int) ($segment['start_page'] ?? 1));
            $endPage = min($pageCount, max($startPage, (int) ($segment['end_page'] ?? $startPage)));

            return [
                'employee_name' => trim((string) ($segment['employee_name'] ?? 'documento')) ?: 'documento',
                'start_page' => $startPage,
                'end_page' => $endPage,
            ];
        }, $segments));
    }

    private function createEmptyExtractedData(SubDocument $subDocument): void
    {
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
    }

    public function documentDisk(): string
    {
        return config('filesystems.default', 'local');
    }

    /**
     * Split the document using the configured classifier.
     *
     * @return array<int, array{employee_name: string, start_page: int, end_page: int}>
     */
    private function splitDocument(string $pdfPath): array
    {
        if (config('services.documents.classifier_driver', 'fake') === 'fake') {
            return [
                ['employee_name' => 'Mario Rossi', 'start_page' => 1, 'end_page' => 1],
            ];
        }

        return $this->bedrock->splitDocument($pdfPath);
    }

    /**
     * Extract fields from the document using the configured OCR driver.
     *
     * @return array{employee_first_name: ?string, employee_last_name: ?string, company_name: ?string, document_date: ?string, document_type: ?string, description: ?string, confidence_score: ?int}
     */
    private function extractFields(string $subPdfPath): array
    {
        if (config('services.documents.ocr_driver', 'local') === 'local') {
            return $this->fallbackExtractedFields();
        }

        return $this->bedrock->extractFields($subPdfPath);
    }

    /**
     * Return deterministic local fields for the simulation driver.
     *
     * @return array{employee_first_name: ?string, employee_last_name: ?string, company_name: ?string, document_date: ?string, document_type: ?string, description: ?string, confidence_score: ?int}
     */
    private function fallbackExtractedFields(): array
    {
        return [
            'employee_first_name' => 'Mario',
            'employee_last_name' => 'Rossi',
            'company_name' => 'Azienda Demo Srl',
            'document_date' => now()->toDateString(),
            'document_type' => 'Cedolino',
            'description' => 'Dati estratti in modalita PoC.',
            'confidence_score' => (int) config('services.bedrock.poc_confidence_threshold', 80),
        ];
    }

    /**
     * Extract a page range from a PDF and write it to storage.
     *
     * @return string Relative path within the configured document disk
     */
    private function extractPages(string $sourcePath, int $originalId, string $employeeName, int $startPage, int $endPage): string
    {
        $pdf = new Fpdi;
        $absoluteSource = $this->copyStorageFileToTemporaryPath($sourcePath);
        $absoluteDest = $this->temporaryPath('split_');

        try {
            $pageCount = $pdf->setSourceFile($absoluteSource);

            for ($page = $startPage; $page <= min($endPage, $pageCount); $page++) {
                $tplIdx = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($tplIdx);
                $pdf->AddPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);
                $pdf->useTemplate($tplIdx);
            }

            $slug = preg_replace('/[^a-z0-9_]/i', '_', $employeeName) ?: 'documento';
            $relativePath = "documents/sub/{$originalId}_{$slug}_{$startPage}-{$endPage}_".Str::uuid().'.pdf';

            $pdf->Output($absoluteDest, 'F');

            if (! Storage::disk($this->documentDisk())->put($relativePath, File::get($absoluteDest))) {
                throw new \RuntimeException("Impossibile salvare lo split PDF: {$relativePath}");
            }

            return $relativePath;
        } finally {
            File::delete([$absoluteSource, $absoluteDest]);
        }
    }

    private function pageCount(string $sourcePath): int
    {
        $pdf = new Fpdi;
        $absoluteSource = $this->copyStorageFileToTemporaryPath($sourcePath);

        try {
            return max(1, $pdf->setSourceFile($absoluteSource));
        } finally {
            File::delete($absoluteSource);
        }
    }

    /**
     * @throws \RuntimeException when the source file cannot be read from storage.
     */
    private function copyStorageFileToTemporaryPath(string $storagePath): string
    {
        $contents = Storage::disk($this->documentDisk())->get($storagePath);

        if ($contents === null || $contents === false) {
            throw new \RuntimeException("File non trovato sullo storage documenti: {$storagePath}");
        }

        $temporaryPath = $this->temporaryPath('source_');
        File::put($temporaryPath, $contents);

        return $temporaryPath;
    }

    /**
     * @throws \RuntimeException when the temporary file cannot be created.
     */
    private function temporaryPath(string $prefix): string
    {
        $directory = storage_path('app/tmp/poc-processing');
        File::ensureDirectoryExists($directory);

        $path = tempnam($directory, $prefix);

        if ($path === false) {
            throw new \RuntimeException('Impossibile creare un file temporaneo per il processamento PDF.');
        }

        return $path;
    }
}
