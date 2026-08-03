<?php

namespace App\Filament\Resources\EquipmentUnitResource\Pages;

use App\Filament\Resources\EquipmentUnitResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEquipmentUnit extends EditRecord
{
    protected static string $resource = EquipmentUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
