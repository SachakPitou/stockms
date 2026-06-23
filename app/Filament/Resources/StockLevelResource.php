<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockLevelResource\Pages;
use App\Models\StockLevel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StockLevelResource extends Resource
{
    protected static ?string $model = StockLevel::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Stock Levels';
    protected static ?int $navigationSort = 3;

    // Stock levels are managed by the system — no manual create
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.category.name')
                    ->label('Category')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('In Stock')
                    ->sortable()
                    ->color(fn (StockLevel $record): string => match(true) {
                        $record->quantity <= 0                              => 'danger',
                        $record->quantity <= $record->product->reorder_point => 'warning',
                        default                                             => 'success',
                    })
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('reserved')
                    ->label('Reserved')
                    ->sortable()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('available')
                    ->label('Available')
                    ->getStateUsing(fn (StockLevel $record) => $record->quantity - $record->reserved)
                    ->color(fn (StockLevel $record): string =>
                        ($record->quantity - $record->reserved) <= 0 ? 'danger' : 'success'
                    )
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('product.reorder_point')
                    ->label('Reorder At')
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.unit')
                    ->label('Unit')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('product.supplier.name')
                    ->label('Supplier')
                    ->searchable(),

                Tables\Columns\IconColumn::make('low_stock')
                    ->label('Low Stock')
                    ->getStateUsing(fn (StockLevel $record) =>
                        $record->quantity <= $record->product->reorder_point
                    )
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('warehouse')
                    ->relationship('warehouse', 'name'),

                Tables\Filters\SelectFilter::make('category')
                    ->relationship('product.category', 'name'),

                Tables\Filters\Filter::make('low_stock')
                    ->label('Low Stock Only')
                    ->query(fn ($query) =>
                        $query->whereHas('product', fn ($q) =>
                            $q->whereRaw('stock_levels.quantity <= products.reorder_point')
                        )
                    )
                    ->toggle(),

                Tables\Filters\Filter::make('out_of_stock')
                    ->label('Out of Stock')
                    ->query(fn ($query) => $query->where('quantity', '<=', 0))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('addStock')
                    ->label('Add Stock')
                    ->icon('heroicon-m-plus')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity to Add')
                            ->numeric()
                            ->minValue(1)
                            ->required(),

                        Forms\Components\TextInput::make('reference')
                            ->label('Reference')
                            ->placeholder('Invoice no., PO number, etc.')
                            ->maxLength(255),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2),
                    ])
                    ->action(function (StockLevel $record, array $data) {
                        app(\App\Services\StockService::class)->adjust(
                            productId:   $record->product_id,
                            warehouseId: $record->warehouse_id,
                            quantity:    $data['quantity'],
                            type:        'receipt',
                            reference:   $data['reference'] ?? null,
                            notes:       $data['notes'] ?? null,
                        );

                        \Filament\Notifications\Notification::make()
                            ->title("Added {$data['quantity']} units to {$record->product->name}")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('removeStock')
                    ->label('Remove Stock')
                    ->icon('heroicon-m-minus')
                    ->color('danger')
                    ->form([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity to Remove')
                            ->numeric()
                            ->minValue(1)
                            ->required(),

                        Forms\Components\TextInput::make('reference')
                            ->label('Reference')
                            ->maxLength(255),

                        Forms\Components\Textarea::make('notes')
                            ->rows(2),
                    ])
                    ->action(function (StockLevel $record, array $data) {
                        app(\App\Services\StockService::class)->adjust(
                            productId:   $record->product_id,
                            warehouseId: $record->warehouse_id,
                            quantity:    $data['quantity'],
                            type:        'issue',
                            reference:   $data['reference'] ?? null,
                            notes:       $data['notes'] ?? null,
                        );

                        \Filament\Notifications\Notification::make()
                            ->title("Removed {$data['quantity']} units from {$record->product->name}")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('history')
                    ->label('History')
                    ->icon('heroicon-m-clock')
                    ->color('gray')
                    ->url(fn (StockLevel $record) =>
                        route('filament.admin.resources.stock-movements.index') .
                        '?tableFilters[product][value]=' . $record->product_id
                    ),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No stock levels found')
            ->emptyStateDescription('Stock levels are created automatically when you add stock movements.')
            ->emptyStateIcon('heroicon-o-archive-box');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockLevels::route('/'),
        ];
    }
}