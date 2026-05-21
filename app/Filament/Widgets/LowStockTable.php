<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LowStockTable extends BaseWidget
{
    protected static ?string $heading = 'Low Stock — Needs Reordering';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->where('is_active', true)
                    ->whereHas('stockLevels', function (Builder $q) {
                        $q->whereRaw('stock_levels.quantity <= products.reorder_point');
                    })
                    ->with(['stockLevels.warehouse', 'supplier', 'category'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier'),

                Tables\Columns\TextColumn::make('stockLevels')
                    ->label('Current Stock')
                    ->formatStateUsing(fn ($record) =>
                        $record->stockLevels->sum('quantity')
                    )
                    ->color('danger'),

                Tables\Columns\TextColumn::make('reorder_point')
                    ->label('Reorder At'),

                Tables\Columns\TextColumn::make('reorder_qty')
                    ->label('Order Qty'),

                Tables\Columns\TextColumn::make('supplier.lead_time_days')
                    ->label('Lead Time')
                    ->suffix(' days'),
            ])
            ->actions([
                Tables\Actions\Action::make('createPO')
                    ->label('Create PO')
                    ->icon('heroicon-m-plus')
                    ->color('warning')
                    ->url(fn (Product $record) =>
                        route('filament.admin.resources.purchase-orders.create')
                    ),
            ])
            ->emptyStateHeading('All stock levels are healthy')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->emptyStateDescription('No products are below their reorder point.');
    }
}