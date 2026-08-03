<?php

namespace App\Filament\Resources\EquipmentIssuanceResource\Pages;

use App\Filament\Resources\EquipmentIssuanceResource;
use App\Models\Product;
use App\Services\StockService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateEquipmentIssuance extends CreateRecord
{
    protected static string $resource = EquipmentIssuanceResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $product = Product::findOrFail($data['product_id']);

        if ($product->is_serialized) {
            $unitIds = collect($data['units'] ?? [])
                ->pluck('equipment_unit_id')
                ->filter()
                ->values()
                ->all();

            if (empty($unitIds)) {
                Notification::make()
                    ->title('No serial numbers selected')
                    ->body('Please select at least one serial number.')
                    ->danger()
                    ->send();

                $this->halt();
            }

            $issuances = app(StockService::class)->issueUnitsToCustomer(
                customerId:          $data['customer_id'],
                productId:           $data['product_id'],
                warehouseId:         $data['warehouse_id'],
                equipmentUnitIds:    $unitIds,
                issuedDate:          $data['issued_date'],
                expectedReturnDate:  $data['expected_return_date'] ?? null,
                notes:               $data['notes'] ?? null,
            );

            // Filament's create page expects a single model back — return the first,
            // the rest were already persisted inside issueUnitsToCustomer().
            return $issuances->first();
        }

        // Non-serialized product — existing bulk-quantity flow
        return app(StockService::class)->issueToCustomer(
            customerId:   $data['customer_id'],
            productId:    $data['product_id'],
            warehouseId:  $data['warehouse_id'],
            quantity:     $data['quantity'],
            issuedDate:   $data['issued_date'],
            serialNumber: $data['serial_number'] ?? null,
            notes:        $data['notes'] ?? null,
        );
    }
}