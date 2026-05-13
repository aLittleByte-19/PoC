<?php

namespace App\Filament\Resources\OriginalDocumentResource\Pages;

use App\Filament\Resources\OriginalDocumentResource;
use App\Jobs\ProcessOriginalDocumentJob;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateOriginalDocument extends CreateRecord
{
    protected static string $resource = OriginalDocumentResource::class;

    protected function afterCreate(): void
    {
        ProcessOriginalDocumentJob::dispatch($this->record);

        Notification::make()
            ->title('Documento in elaborazione')
            ->body('Il processamento è stato inviato alla queue Redis.')
            ->success()
            ->send();
    }
}
