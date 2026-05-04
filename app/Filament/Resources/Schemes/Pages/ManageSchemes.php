<?php

namespace App\Filament\Resources\Schemes\Pages;

use App\Filament\Resources\Schemes\SchemeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSchemes extends ManageRecords
{
    protected static string $resource = SchemeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
