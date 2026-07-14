<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LowStockTable extends BaseWidget
{
    protected static ?string $heading = '⚠ Products Running Low — Action Required';
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
                Tables\Columns\TextColumn::make('name')
                    ->label('Product')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color('gray')
                    ->placeholder('No category'),

                Tables\Columns\TextColumn::make('stockLevels')
                    ->label('Current Stock')
                    ->formatStateUsing(fn ($record) =>
                        $record->stockLevels->sum('quantity') . ' ' . $record->unit
                    )
                    ->color('danger')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('reorder_point')
                    ->label('Should Not Go Below')
                    ->formatStateUsing(fn ($record) =>
                        $record->reorder_point . ' ' . $record->unit
                    )
                    ->color('warning'),

                Tables\Columns\TextColumn::make('reorder_qty')
                    ->label('Suggested Order Qty')
                    ->formatStateUsing(fn ($record) =>
                        $record->reorder_qty . ' ' . $record->unit
                    ),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Order From')
                    ->placeholder('No supplier set'),

                Tables\Columns\TextColumn::make('supplier.lead_time_days')
                    ->label('Delivery Time')
                    ->formatStateUsing(fn ($record) =>
                        $record->supplier
                            ? $record->supplier->lead_time_days . ' days'
                            : '—'
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('createOrder')
                    ->label('Create Order')
                    ->icon('heroicon-m-shopping-cart')
                    ->color('warning')
                    ->url(fn (Product $record) =>
                        route('filament.admin.resources.purchase-orders.create')
                    ),
            ])
            ->emptyStateHeading('All products have sufficient stock')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->emptyStateDescription('Nothing needs to be restocked right now.');
    }
}