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

            // Move to Ordered
            Actions\Action::make('markOrdered')
                ->label('Mark as Ordered')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->visible(fn () =>
                    $this->record->status === 'draft' &&
                    auth()->user()->hasAnyRole(['Admin', 'Approval Team'])
                )
                ->requiresConfirmation()
                ->modalDescription('Confirm you have sent this order to the supplier.')
                ->action(function () {
                    $this->record->update(['status' => 'ordered']);
                    Notification::make()
                        ->title('Status updated to Ordered')
                        ->success()->send();
                    $this->refreshFormData(['status']);
                }),

            // Move to Shipped
            Actions\Action::make('markShipped')
                ->label('Mark as Shipped')
                ->icon('heroicon-o-truck')
                ->color('warning')
                ->visible(fn () =>
                    $this->record->status === 'ordered' &&
                    auth()->user()->hasAnyRole(['Admin', 'Approval Team'])
                )
                ->form([
                    Forms\Components\TextInput::make('tracking_number')
                        ->label('Tracking Number')
                        ->placeholder('e.g. DHL-001234')
                        ->required(),

                    Forms\Components\DatePicker::make('expected_date')
                        ->label('Expected Arrival Date')
                        ->required()
                        ->displayFormat('d M Y'),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'status'          => 'shipped',
                        'tracking_number' => $data['tracking_number'],
                        'expected_date'   => $data['expected_date'],
                    ]);
                    Notification::make()
                        ->title('Status updated to Shipped')
                        ->body("Tracking: {$data['tracking_number']}")
                        ->success()->send();
                    $this->refreshFormData(['status', 'tracking_number', 'expected_date']);
                }),

            // Receive Stock
            Actions\Action::make('receiveStock')
                ->label('Mark as Received — Add to Stock')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(fn () =>
                    $this->record->status === 'shipped' &&
                    auth()->user()->hasAnyRole(['Admin', 'Approval Team'])
                )
                ->form(function () {
                    $fields = [
                        Forms\Components\Placeholder::make('info')
                            ->label('')
                            ->content('Enter the quantity received for each item. Stock will be added automatically.'),
                    ];

                    foreach ($this->record->items()->with('product')->get() as $item) {
                        $remaining = $item->qty_ordered - $item->qty_received;
                        if ($remaining <= 0) continue;

                        $fields[] = Forms\Components\TextInput::make('item_' . $item->id)
                            ->label("{$item->product->name} (ordered: {$item->qty_ordered}, remaining: {$remaining})")
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
                        if (str_starts_with($key, 'item_') && (int) $qty > 0) {
                            $itemId                = (int) str_replace('item_', '', $key);
                            $receivedQtys[$itemId] = (int) $qty;
                        }
                    }

                    if (empty($receivedQtys)) {
                        Notification::make()
                            ->title('No quantities entered')
                            ->body('Please enter the quantity received for at least one item.')
                            ->warning()
                            ->send();
                        return;
                    }

                    app(\App\Services\StockService::class)->receivePurchaseOrder(
                        $this->record,
                        $receivedQtys
                    );

                    // ── Auto-request transfer to the requester's actual warehouse ──────
                    $destinationId = $this->record->destination_warehouse_id;

                    if ($destinationId && $destinationId !== $this->record->warehouse_id) {
                        foreach ($receivedQtys as $itemId => $qty) {
                            $item = $this->record->items()->find($itemId);
                            if (! $item) continue;

                            \App\Models\StockTransfer::create([
                                'product_id'        => $item->product_id,
                                'from_warehouse_id' => $this->record->warehouse_id,
                                'to_warehouse_id'   => $destinationId,
                                'quantity'          => $qty,
                                'reason'            => "Auto-requested from PO #{$this->record->po_number}",
                                'requested_by'      => auth()->id(),
                                'status'            => 'pending',
                            ]);
                        }

                        Notification::make()
                            ->title('Stock received — transfer request created')
                            ->body('A pending transfer to the requested warehouse has been created for HR to approve.')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Stock received and added to warehouse')
                            ->body('Stock levels have been updated.')
                            ->success()
                            ->send();
                    }

                    $this->refreshFormData(['status', 'received_date']);
                }),
            // Cancel
            Actions\Action::make('cancel')
                ->label('Cancel Order')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () =>
                    !in_array($this->record->status, ['received', 'cancelled']) &&
                    auth()->user()->hasAnyRole(['Admin', 'Approval Team'])
                )
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['status' => 'cancelled']);
                    Notification::make()
                        ->title('Order cancelled')
                        ->danger()->send();
                    $this->refreshFormData(['status']);
                }),
        ];
    }
}