<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Purchasing';
    protected static ?string $navigationLabel = 'Purchase Orders';
    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->check() &&
            auth()->user()->hasAnyRole([
                'Admin', 'HR Approver', 'HR Verifier',
            ]);
    }

    public static function canCreate(): bool
    {
        return auth()->check() &&
            auth()->user()->hasAnyRole(['Admin', 'HR Approver', 'HR Verifier']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Order Information')
                ->schema([
                    Forms\Components\TextInput::make('po_number')
                        ->label('PO Number')
                        ->default('PO-' . strtoupper(Str::random(8)))
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->disabled(fn (string $operation) => $operation === 'edit')
                        ->dehydrated(),

                    Forms\Components\Select::make('status')
                        ->label('Order Status')
                        ->options([
                            'draft'     => '📝 Draft',
                            'ordered'   => '📤 Ordered — sent to supplier',
                            'shipped'   => '🚢 Shipped — on the way',
                            'received'  => '✅ Received',
                            'cancelled' => '❌ Cancelled',
                        ])
                        ->default('draft')
                        ->disabled(fn (string $operation) => $operation === 'edit')
                        ->dehydrated(),

                    Forms\Components\Select::make('supplier_id')
                        ->label('Supplier')
                        ->options(Supplier::where('is_active', true)->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabled(fn (string $operation) => $operation === 'edit')
                        ->dehydrated(),

                    Forms\Components\Select::make('warehouse_id')
                        ->label('Deliver To')
                        ->options(Warehouse::pluck('name', 'id'))
                        ->required()
                        ->disabled(fn (string $operation) => $operation === 'edit')
                        ->dehydrated()
                        ->helperText('All incoming stock is received here first.'),

                    Forms\Components\Select::make('destination_warehouse_id')
                        ->label('Ultimately For (optional)')
                        ->options(Warehouse::pluck('name', 'id'))
                        ->nullable()
                        ->disabled(fn (string $operation) => $operation === 'edit')
                        ->dehydrated()
                        ->helperText('If this stock is meant for a different branch than the delivery warehouse, select it here. A transfer will be suggested after receiving.'),

                    Forms\Components\DatePicker::make('order_date')
                        ->label('Order Date')
                        ->default(now())
                        ->displayFormat('d M Y')
                        ->required()
                        ->disabled(fn (string $operation) => $operation === 'edit')
                        ->dehydrated(),

                    Forms\Components\DatePicker::make('expected_date')
                        ->label('Expected Arrival')
                        ->displayFormat('d M Y'),

                    Forms\Components\TextInput::make('tracking_number')
                        ->label('Tracking Number')
                        ->placeholder('e.g. DHL-001234'),
                ])->columns(2),

            Forms\Components\Section::make('Items Being Ordered')
                ->description('Add each product and the quantity you are ordering. Not editable after creation — use Receive Stock to record what arrives.')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->relationship()
                        ->schema([
                            Forms\Components\Select::make('product_id')
                                ->label('Product')
                                ->options(
                                    Product::where('is_active', true)
                                        ->get()
                                        ->mapWithKeys(fn ($p) => [
                                            $p->id => $p->name . ' (' . $p->unit . ')'
                                        ])
                                )
                                ->searchable()
                                ->preload()
                                ->required()
                                ->columnSpan(4),

                            Forms\Components\TextInput::make('qty_ordered')
                                ->label('Quantity')
                                ->numeric()
                                ->required()
                                ->default(1)
                                ->minValue(1)
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('unit_price')
                                ->label('Unit Price')
                                ->numeric()
                                ->prefix('$')
                                ->default(0)
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('customisation')
                                ->label('Special Instructions')
                                ->placeholder('e.g. Pre-configured, branded, specific colour')
                                ->columnSpan(4),
                        ])
                        ->columns(8)
                        ->addActionLabel('+ Add Another Product')
                        ->defaultItems(1)
                        ->disabled(fn (string $operation) => $operation === 'edit')
                        ->dehydrated(),
                ]),

            Forms\Components\Section::make('Costs & Currency')
                ->collapsed()
                ->schema([
                    Forms\Components\Select::make('currency')
                        ->options([
                            'USD' => 'USD', 'KHR' => 'KHR',
                            'CNY' => 'CNY', 'THB' => 'THB',
                            'SGD' => 'SGD',
                        ])
                        ->default('USD'),

                    Forms\Components\TextInput::make('exchange_rate')
                        ->numeric()->default(1),

                    Forms\Components\TextInput::make('freight_cost')
                        ->numeric()->prefix('$')->default(0),

                    Forms\Components\TextInput::make('customs_duty')
                        ->numeric()->prefix('$')->default(0),

                    Forms\Components\TextInput::make('total')
                        ->label('Total Order Value')
                        ->numeric()
                        ->prefix('$')
                        ->default(0),
                ])->columns(2),

            Forms\Components\Section::make('Notes')
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->rows(3)
                        ->columnSpanFull(),
                ])->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('po_number')
                    ->label('PO Number')
                    ->searchable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'draft'     => 'Draft',
                        'ordered'   => 'Ordered',
                        'shipped'   => 'Shipped',
                        'received'  => 'Received',
                        'cancelled' => 'Cancelled',
                        default     => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match($state) {
                        'draft'     => 'gray',
                        'ordered'   => 'info',
                        'shipped'   => 'warning',
                        'received'  => 'success',
                        'cancelled' => 'danger',
                        default     => 'gray',
                    }),

                Tables\Columns\TextColumn::make('expected_date')
                    ->label('Expected Arrival')
                    ->date('d M Y')
                    ->placeholder('Not set')
                    ->color(fn ($record) =>
                        $record->expected_date &&
                        $record->expected_date->isPast() &&
                        !in_array($record->status, ['received', 'cancelled'])
                            ? 'danger' : null
                    ),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Deliver To')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('destinationWarehouse.name')
                    ->label('Ultimately For')
                    ->badge()
                    ->color('warning')
                    ->placeholder('—')
                    ->visible(fn ($record) => $record && $record->destination_warehouse_id !== $record->warehouse_id),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('tracking_number')
                    ->label('Tracking')
                    ->placeholder('—')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft'     => 'Draft',
                        'ordered'   => 'Ordered',
                        'shipped'   => 'Shipped',
                        'received'  => 'Received',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Edit / Manage'),
            ]);
    }
    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'edit'   => Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
    
}