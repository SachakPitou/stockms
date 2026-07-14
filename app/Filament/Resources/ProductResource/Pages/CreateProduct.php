<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-generate SKU if left blank
        if (empty($data['sku'])) {
            $data['sku'] = 'PRD-' . strtoupper(Str::random(6));
        }

        return $data;
    }
}