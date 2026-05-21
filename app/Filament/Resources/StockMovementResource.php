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
    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Stock Movements';

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
                    ->label('Date')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'success' => 'receipt',
                        'danger'  => 'issue',
                        'warning' => 'adjustment',
                        'info'    => 'transfer',
                        'gray'    => 'return',
                    ]),

                Tables\Columns\TextColumn::make('quantity')
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity_before')
                    ->label('Before'),

                Tables\Columns\TextColumn::make('quantity_after')
                    ->label('After'),

                Tables\Columns\TextColumn::make('reference')
                    ->searchable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('By'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'receipt'    => 'Receipt',
                        'issue'      => 'Issue',
                        'adjustment' => 'Adjustment',
                        'transfer'   => 'Transfer',
                        'return'     => 'Return',
                    ]),

                Tables\Filters\SelectFilter::make('warehouse')
                    ->relationship('warehouse', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListStockMovements::route('/'),
            'create' => Pages\CreateStockMovement::route('/create'),
        ];
    }
}