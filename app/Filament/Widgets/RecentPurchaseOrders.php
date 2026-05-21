<?php

namespace App\Filament\Widgets;

use App\Models\PurchaseOrder;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentPurchaseOrders extends BaseWidget
{
    protected static ?string $heading = 'Recent Purchase Orders';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PurchaseOrder::query()
                    ->with(['supplier', 'warehouse', 'items'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('po_number')
                    ->label('PO Number')
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft'              => 'gray',
                        'sent'               => 'info',
                        'confirmed'          => 'primary',
                        'shipped'            => 'warning',
                        'partially_received' => 'warning',
                        'received'           => 'success',
                        'cancelled'          => 'danger',
                        default              => 'gray',
                    }),

                Tables\Columns\TextColumn::make('order_date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expected_date')
                    ->label('Expected')
                    ->date('d M Y'),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items'),

                Tables\Columns\TextColumn::make('total')
                    ->money('USD'),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->badge()
                    ->color('gray'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-m-eye')
                    ->url(fn (PurchaseOrder $record) =>
                        route('filament.admin.resources.purchase-orders.edit', $record)
                    ),
            ]);
    }
}