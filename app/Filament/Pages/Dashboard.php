<?php

namespace App\Filament\Pages;

use App\Enums\CommunicationStatus;
use App\Enums\ProcessingStatus;
use App\Enums\SendStatus;
use App\Models\Communication;
use App\Models\OriginalDocument;
use App\Models\SubDocument;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationGroup = 'Panoramica';

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Overview';

    protected static ?int $navigationSort = -10;

    protected static ?string $title = 'Overview operativa';

    protected static string $view = 'filament.pages.dashboard';

    public function getAssistantMetrics(): array
    {
        $total = Communication::query()->count();
        $drafts = Communication::query()
            ->where('status', CommunicationStatus::Draft)
            ->count();

        return [
            ['value' => $total, 'label' => 'Contenuti generati'],
            ['value' => $drafts, 'label' => 'Bozze da rivedere'],
            ['value' => max(0, $total - $drafts), 'label' => 'Contenuti finalizzati'],
        ];
    }

    public function getCopilotMetrics(): array
    {
        $documents = OriginalDocument::query()->count();
        $failed = OriginalDocument::query()
            ->where('processing_status', ProcessingStatus::Failed)
            ->count();
        $pendingDispatch = SubDocument::query()
            ->where('send_status', SendStatus::Pending)
            ->count();

        return [
            ['value' => $documents, 'label' => 'Documenti caricati'],
            ['value' => $pendingDispatch, 'label' => 'Invii da completare'],
            ['value' => $failed, 'label' => 'Anomalie da verificare'],
        ];
    }

    public function getRecentCommunications(): array
    {
        return Communication::query()
            ->latest()
            ->limit(3)
            ->get(['generated_title', 'tone', 'created_at'])
            ->map(fn (Communication $communication): array => [
                'title' => $communication->generated_title ?: 'Bozza senza titolo',
                'meta' => trim(($communication->tone ?: 'Tono non indicato').' - '.$communication->created_at?->format('d/m/Y H:i')),
            ])
            ->all();
    }

    public function getRecentDocuments(): array
    {
        return OriginalDocument::query()
            ->latest()
            ->limit(3)
            ->get(['original_filename', 'processing_status', 'created_at'])
            ->map(fn (OriginalDocument $document): array => [
                'title' => $document->original_filename,
                'meta' => $document->processing_status->label().' - '.$document->created_at?->format('d/m/Y H:i'),
            ])
            ->all();
    }
}
