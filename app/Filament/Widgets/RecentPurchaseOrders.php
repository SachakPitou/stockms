<?php

namespace App\Filament\Widgets;

use App\Models\PurchaseOrder;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentPurchaseOrders extends BaseWidget
{
    protected static ?string $heading = 'Recent Orders';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PurchaseOrder::query()
                    ->with(['supplier', 'warehouse', 'items'])
                    ->latest()
                    ->limit(8)
            )
            ->columns([
                Tables\Columns\TextColumn::make('po_number')
                    ->label('Order No.')
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'draft'              => 'Draft',
                        'sent'               => 'Sent to Supplier',
                        'confirmed'          => 'Confirmed',
                        'shipped'            => 'Shipped',
                        'partially_received' => 'Partially Received',
                        'received'           => 'Fully Received',
                        'cancelled'          => 'Cancelled',
                        default              => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match($state) {
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
                    ->label('Ordered')
                    ->date('d M Y'),

                Tables\Columns\TextColumn::make('expected_date')
                    ->label('Expected')
                    ->date('d M Y')
                    ->placeholder('Not set')
                    ->color(fn ($record) =>
                        $record->expected_date &&
                        $record->expected_date->isPast() &&
                        !in_array($record->status, ['received', 'cancelled'])
                            ? 'danger' : null
                    ),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Deliver To')
                    ->badge()
                    ->color('info'),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (PurchaseOrder $record) =>
                        route('filament.admin.resources.purchase-orders.edit', $record)
                    ),
            ])
            ->emptyStateHeading('No orders yet')
            ->emptyStateIcon('heroicon-o-shopping-cart');
    }
}