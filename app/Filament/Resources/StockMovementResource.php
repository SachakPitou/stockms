<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Stock';
    protected static ?string $navigationLabel = 'Stock History';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Movement Details')
                ->schema([
                    Forms\Components\Select::make('product_id')
                        ->label('Product')
                        ->options(Product::where('is_active', true)->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\Select::make('warehouse_id')
                        ->label('Warehouse')
                        ->options(Warehouse::pluck('name', 'id'))
                        ->required(),

                    Forms\Components\Select::make('type')
                        ->options([
                            'receipt'    => 'Receipt — stock coming in',
                            'issue'      => 'Issue — stock going out',
                            'adjustment' => 'Adjustment — correction',
                            'transfer'   => 'Transfer — between warehouses',
                            'return'     => 'Return — customer return',
                        ])
                        ->required(),

                    Forms\Components\TextInput::make('quantity')
                        ->numeric()
                        ->required()
                        ->helperText('Use positive number. Direction is set by the type above.'),

                    Forms\Components\TextInput::make('reference')
                        ->maxLength(255)
                        ->placeholder('Invoice no., PO number, etc.'),

                    Forms\Components\Textarea::make('notes')
                        ->rows(2)
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('moved_at')
                    ->label('Date & Time')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Location')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Action')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'receipt'    => 'Stock Added',
                        'issue'      => 'Stock Removed',
                        'adjustment' => 'Correction',
                        'transfer'   => 'Transferred',
                        'return'     => 'Returned',
                        default      => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match($state) {
                        'receipt'    => 'success',
                        'issue'      => 'danger',
                        'adjustment' => 'warning',
                        'transfer'   => 'info',
                        'return'     => 'gray',
                        default      => 'gray',
                    }),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qty Changed')
                    ->formatStateUsing(fn ($state) =>
                        ($state > 0 ? '+' : '') . $state
                    )
                    ->color(fn ($state): string =>
                        $state > 0 ? 'success' : 'danger'
                    )
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('quantity_before')
                    ->label('Before'),

                Tables\Columns\TextColumn::make('quantity_after')
                    ->label('After'),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Reason / Notes')
                    ->limit(40)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Reference')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Done By')
                    ->placeholder('System'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Filter by Action')
                    ->options([
                        'receipt'    => 'Stock Added',
                        'issue'      => 'Stock Removed',
                        'adjustment' => 'Correction',
                        'transfer'   => 'Transferred',
                        'return'     => 'Returned',
                    ]),

                Tables\Filters\SelectFilter::make('warehouse')
                    ->relationship('warehouse', 'name')
                    ->label('Filter by Location'),

                Tables\Filters\Filter::make('today')
                    ->label('Today only')
                    ->query(fn ($query) => $query->whereDate('moved_at', today()))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Details'),
            ])
            ->emptyStateHeading('No stock history yet')
            ->emptyStateDescription('Every time stock is added or removed it will appear here.')
            ->emptyStateIcon('heroicon-o-clock');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListStockMovements::route('/'),
            'create' => Pages\CreateStockMovement::route('/create'),
        ];
    }
}