<?php

namespace App\Filament\Resources\StockMovementResource\Pages;

use App\Filament\Resources\StockMovementResource;
use App\Services\StockService;
use Filament\Resources\Pages\CreateRecord;

class CreateStockMovement extends CreateRecord
{
    protected static string $resource = StockMovementResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(StockService::class)->adjust(
            productId:   $data['product_id'],
            warehouseId: $data['warehouse_id'],
            quantity:    $data['quantity'],
            type:        $data['type'],
            reference:   $data['reference'] ?? null,
            notes:       $data['notes'] ?? null,
        );
    }
}