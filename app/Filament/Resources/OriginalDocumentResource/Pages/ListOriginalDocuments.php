<?php

namespace App\Filament\Resources\OriginalDocumentResource\Pages;

use App\Filament\Resources\OriginalDocumentResource;
use Filament\Resources\Pages\ListRecords;

class ListOriginalDocuments extends ListRecords
{
    protected static string $resource = OriginalDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
