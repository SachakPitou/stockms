<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use App\Services\StockService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('receiveStock')
                ->label('Receive Stock')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(fn () => in_array(
                    $this->record->status,
                    ['sent', 'confirmed', 'shipped', 'partially_received']
                ))
                ->form(function () {
                    $fields = [];

                    foreach ($this->record->items()->with('product')->get() as $item) {
                        $remaining = $item->qty_ordered - $item->qty_received;
                        if ($remaining <= 0) continue;

                        $fields[] = Forms\Components\TextInput::make("qty_{$item->id}")
                            ->label(
                                "{$item->product->name} — " .
                                "ordered: {$item->qty_ordered} | " .
                                "received: {$item->qty_received} | " .
                                "remaining: {$remaining}"
                            )
                            ->numeric()
                            ->default($remaining)
                            ->minValue(0)
                            ->maxValue($remaining);
                    }

                    return $fields;
                })
                ->action(function (array $data) {
                    $receivedQtys = [];

                    foreach ($data as $key => $qty) {
                        if (str_starts_with($key, 'qty_') && (int) $qty > 0) {
                            $itemId                = (int) str_replace('qty_', '', $key);
                            $receivedQtys[$itemId] = (int) $qty;
                        }
                    }

                    app(StockService::class)->receivePurchaseOrder(
                        $this->record,
                        $receivedQtys
                    );

                    Notification::make()
                        ->title('Stock received successfully')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'received_date']);
                }),

            Actions\DeleteAction::make(),
        ];
    }
}