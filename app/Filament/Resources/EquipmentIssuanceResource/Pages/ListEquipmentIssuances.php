<?php

namespace App\Filament\Resources\EquipmentIssuanceResource\Pages;

use App\Filament\Resources\EquipmentIssuanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEquipmentIssuances extends ListRecords
{
    protected static string $resource = EquipmentIssuanceResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Issue Equipment to Customer')];
    }
}
