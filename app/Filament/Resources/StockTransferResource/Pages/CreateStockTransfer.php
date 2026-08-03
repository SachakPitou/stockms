<?php

namespace App\Filament\Resources\StockTransferResource\Pages;

use App\Filament\Resources\StockTransferResource;
use App\Models\Product;
use Filament\Resources\Pages\CreateRecord;

class CreateStockTransfer extends CreateRecord
{
    protected static string $resource = StockTransferResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['requested_by'] = auth()->id();
        $data['status']       = 'pending';

        $product = Product::find($data['product_id']);
        if ($product?->is_serialized) {
            $data['quantity'] = count($data['equipment_unit_ids'] ?? []);
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $unitIds = $data['equipment_unit_ids'] ?? [];
        unset($data['equipment_unit_ids']);

        $record = static::getModel()::create($data);

        if (! empty($unitIds)) {
            $record->equipmentUnits()->sync($unitIds);
        }

        return $record;
    }
}