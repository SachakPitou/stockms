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
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Purchasing';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Order Information')
                ->schema([
                    Forms\Components\TextInput::make('po_number')
                        ->label('PO Number')
                        ->default('PO-' . strtoupper(Str::random(8)))
                        ->required()
                        ->unique(ignoreRecord: true),

                    Forms\Components\Select::make('status')
                        ->options([
                            'draft'              => 'Draft',
                            'sent'               => 'Sent to Supplier',
                            'confirmed'          => 'Confirmed',
                            'shipped'            => 'Shipped',
                            'partially_received' => 'Partially Received',
                            'received'           => 'Received',
                            'cancelled'          => 'Cancelled',
                        ])
                        ->default('draft')
                        ->required(),

                    Forms\Components\Select::make('supplier_id')
                        ->label('Supplier')
                        ->options(Supplier::where('is_active', true)->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\Select::make('warehouse_id')
                        ->label('Deliver To')
                        ->options(Warehouse::pluck('name', 'id'))
                        ->required(),

                    Forms\Components\DatePicker::make('order_date')
                        ->required()
                        ->default(now()),

                    Forms\Components\DatePicker::make('expected_date')
                        ->label('Expected Arrival'),
                ])->columns(2),

            Forms\Components\Section::make('Overseas / Costs')
                ->schema([
                    Forms\Components\Select::make('currency')
                        ->options([
                            'USD' => 'USD', 'EUR' => 'EUR', 'GBP' => 'GBP',
                            'CNY' => 'CNY', 'KHR' => 'KHR', 'THB' => 'THB',
                        ])
                        ->default('USD')
                        ->required(),

                    Forms\Components\TextInput::make('exchange_rate')
                        ->numeric()
                        ->default(1)
                        ->required()
                        ->helperText('Rate to your local currency'),

                    Forms\Components\TextInput::make('freight_cost')
                        ->label('Freight Cost')
                        ->numeric()
                        ->prefix('$')
                        ->default(0),

                    Forms\Components\TextInput::make('customs_duty')
                        ->label('Customs Duty')
                        ->numeric()
                        ->prefix('$')
                        ->default(0),

                    Forms\Components\TextInput::make('tracking_number')
                        ->maxLength(255)
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make('Order Items')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->relationship()
                        ->schema([
                            Forms\Components\Select::make('product_id')
                                ->label('Product')
                                ->options(Product::where('is_active', true)->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->columnSpan(3),

                            Forms\Components\TextInput::make('qty_ordered')
                                ->label('Qty')
                                ->numeric()
                                ->required()
                                ->default(1)
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('unit_price')
                                ->label('Unit Price')
                                ->numeric()
                                ->prefix('$')
                                ->required()
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('customisation')
                                ->label('Customisation')
                                ->placeholder('e.g. logo print, blue colour')
                                ->columnSpan(3),
                        ])
                        ->columns(9)
                        ->addActionLabel('Add Product')
                        ->defaultItems(1),
                ]),

            Forms\Components\Section::make('Notes')
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
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
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray'    => 'draft',
                        'info'    => 'sent',
                        'primary' => 'confirmed',
                        'warning' => 'shipped',
                        'success' => 'received',
                        'danger'  => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('order_date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expected_date')
                    ->label('Expected')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('currency')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('total')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft'              => 'Draft',
                        'sent'               => 'Sent',
                        'confirmed'          => 'Confirmed',
                        'shipped'            => 'Shipped',
                        'partially_received' => 'Partially Received',
                        'received'           => 'Received',
                        'cancelled'          => 'Cancelled',
                    ]),

                Tables\Filters\SelectFilter::make('supplier')
                    ->relationship('supplier', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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