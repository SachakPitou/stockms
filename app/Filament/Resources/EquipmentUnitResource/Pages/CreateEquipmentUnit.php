<?php

namespace App\Filament\Resources\EquipmentUnitResource\Pages;

use App\Filament\Resources\EquipmentUnitResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEquipmentUnit extends CreateRecord
{
    protected static string $resource = EquipmentUnitResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['added_by'] = auth()->id();
        return $data;
    }
}
