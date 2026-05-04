<?php

namespace App\Filament\Resources\JobRoles\Pages;

use App\Filament\Resources\JobRoles\JobRoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageJobRoles extends ManageRecords
{
    protected static string $resource = JobRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
