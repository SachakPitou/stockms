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
    protected static ?string $navigationGroup = 'Stock';
    protected static ?string $navigationLabel = 'Current Stock';
    protected static ?int $navigationSort = 2;

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
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('product.category.name')
                    ->label('Category')
                    ->badge()
                    ->color('gray')
                    ->placeholder('No category'),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Location')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qty in Stock')
                    ->sortable()
                    ->formatStateUsing(fn (StockLevel $record) =>
                        $record->quantity . ' ' . $record->product->unit
                    )
                    ->color(fn (StockLevel $record): string => match(true) {
                        $record->quantity <= 0                                => 'danger',
                        $record->quantity <= $record->product->reorder_point  => 'warning',
                        default                                               => 'success',
                    })
                    ->weight('bold')
                    ->description(fn (StockLevel $record): string => match(true) {
                        $record->quantity <= 0                                => '⚠ Out of stock',
                        $record->quantity <= $record->product->reorder_point  => '⚠ Running low',
                        default                                               => 'Sufficient',
                    }),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->since()
                    ->sortable(),
            ])

            ->filters([
                Tables\Filters\SelectFilter::make('warehouse')
                    ->relationship('warehouse', 'name')
                    ->label('Filter by Location'),

                Tables\Filters\SelectFilter::make('category')
                    ->relationship('product.category', 'name')
                    ->label('Filter by Category'),

                Tables\Filters\Filter::make('low_stock')
                    ->label('Show Low Stock Only')
                    ->query(fn ($query) =>
                        $query->whereHas('product', fn ($q) =>
                            $q->whereRaw('stock_levels.quantity <= products.reorder_point')
                        )
                    )
                    ->toggle(),

                Tables\Filters\Filter::make('out_of_stock')
                    ->label('Show Out of Stock Only')
                    ->query(fn ($query) => $query->where('quantity', '<=', 0))
                    ->toggle(),
            ])

            ->actions([
                Tables\Actions\Action::make('addStock')
                    ->label('Add Stock')
                    ->icon('heroicon-m-plus-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\Placeholder::make('product_info')
                            ->label('Product')
                            ->content(fn (StockLevel $record) =>
                                $record->product->name . ' — currently ' .
                                $record->quantity . ' ' . $record->product->unit .
                                ' in ' . $record->warehouse->name
                            ),

                        Forms\Components\TextInput::make('quantity')
                            ->label('How many units are you adding?')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->suffix(fn (StockLevel $record) => $record->product->unit),

                        Forms\Components\TextInput::make('reference')
                            ->label('Reference (optional)')
                            ->placeholder('e.g. Invoice number, PO number, delivery note')
                            ->maxLength(255),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes (optional)')
                            ->placeholder('e.g. Received from supplier, transferred from HQ')
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
                            ->title('Stock added successfully')
                            ->body("{$data['quantity']} {$record->product->unit} added to {$record->product->name}")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('removeStock')
                    ->label('Remove Stock')
                    ->icon('heroicon-m-minus-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Placeholder::make('product_info')
                            ->label('Product')
                            ->content(fn (StockLevel $record) =>
                                $record->product->name . ' — currently ' .
                                $record->quantity . ' ' . $record->product->unit .
                                ' in ' . $record->warehouse->name
                            ),

                        Forms\Components\TextInput::make('quantity')
                            ->label('How many units are you removing?')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->suffix(fn (StockLevel $record) => $record->product->unit),

                        Forms\Components\Select::make('reason')
                            ->label('Why are you removing this stock?')
                            ->options([
                                'Used by field technician'     => 'Used by field technician',
                                'Issued to department'         => 'Issued to department',
                                'Damaged or defective'         => 'Damaged or defective',
                                'Lost or missing'              => 'Lost or missing',
                                'Returned to supplier'         => 'Returned to supplier',
                                'Other'                        => 'Other',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('reference')
                            ->label('Reference (optional)')
                            ->placeholder('e.g. Work order number, department name')
                            ->maxLength(255),

                        Forms\Components\Textarea::make('notes')
                            ->label('Additional notes (optional)')
                            ->rows(2),
                    ])
                    ->action(function (StockLevel $record, array $data) {
                        app(\App\Services\StockService::class)->adjust(
                            productId:   $record->product_id,
                            warehouseId: $record->warehouse_id,
                            quantity:    $data['quantity'],
                            type:        'issue',
                            reference:   $data['reference'] ?? null,
                            notes:       ($data['reason'] ?? '') . ($data['notes'] ? ' — ' . $data['notes'] : ''),
                        );

                        \Filament\Notifications\Notification::make()
                            ->title('Stock removed successfully')
                            ->body("{$data['quantity']} {$record->product->unit} removed from {$record->product->name}")
                            ->success()
                            ->send();
                    }),
            ])

            ->bulkActions([])

            ->emptyStateHeading('No stock records yet')
            ->emptyStateDescription('Stock levels appear here automatically once you add stock movements.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockLevels::route('/'),
        ];
    }
}