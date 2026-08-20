<?php

namespace App\Filament\Resources\EquipmentUnitResource\Pages;

use App\Filament\Resources\EquipmentUnitResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEquipmentUnits extends ListRecords
{
    protected static string $resource = EquipmentUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Register New Unit')
                ->visible(fn () => auth()->user()->hasAnyRole(['Admin', 'Approval Team', 'HR Staff'])),
        ];
    }
}