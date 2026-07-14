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
    protected static ?string $navigationLabel = 'Orders';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([

            // ── Section 1: Basic order info ───────────────────────────
            Forms\Components\Section::make('Order Details')
                ->description('Who are we ordering from, where is it going, and when?')
                ->schema([
                    Forms\Components\TextInput::make('po_number')
                        ->label('Order Number')
                        ->default('PO-' . strtoupper(Str::random(8)))
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('Auto-generated — you can change it if needed.'),

                    Forms\Components\Select::make('status')
                        ->label('Order Status')
                        ->options([
                            'draft'              => '📝 Draft — not sent yet',
                            'sent'               => '📤 Sent to Supplier',
                            'confirmed'          => '✅ Confirmed by Supplier',
                            'shipped'            => '🚢 Shipped — on the way',
                            'partially_received' => '📦 Partially Received',
                            'received'           => '✔ Fully Received',
                            'cancelled'          => '❌ Cancelled',
                        ])
                        ->default('draft')
                        ->required(),

                    Forms\Components\Select::make('supplier_id')
                        ->label('Supplier')
                        ->options(Supplier::where('is_active', true)->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('Who are we buying from?'),

                    Forms\Components\Select::make('warehouse_id')
                        ->label('Deliver To')
                        ->options(Warehouse::pluck('name', 'id'))
                        ->required()
                        ->helperText('Which warehouse should receive this order?'),

                    Forms\Components\DatePicker::make('order_date')
                        ->label('Order Date')
                        ->required()
                        ->default(now())
                        ->displayFormat('d M Y'),

                    Forms\Components\DatePicker::make('expected_date')
                        ->label('Expected Arrival Date')
                        ->displayFormat('d M Y')
                        ->helperText('When do you expect the goods to arrive?'),
                ])->columns(2),

            // ── Section 2: What we are ordering ──────────────────────
            Forms\Components\Section::make('Items Being Ordered')
                ->description('Add each product and the quantity you are ordering.')
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
                        ->reorderable(false),
                ]),

            // ── Section 3: Shipping & tracking ───────────────────────
            Forms\Components\Section::make('Shipping & Tracking')
                ->description('Fill in once the order has been shipped.')
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('tracking_number')
                        ->label('Tracking Number')
                        ->placeholder('e.g. DHL-88291744')
                        ->maxLength(255),

                    Forms\Components\DatePicker::make('received_date')
                        ->label('Date Received')
                        ->displayFormat('d M Y'),
                ])->columns(2),

            // ── Section 4: Costs (overseas orders) ───────────────────
            Forms\Components\Section::make('Costs & Currency')
                ->description('For overseas orders — fill in freight, customs and currency details.')
                ->collapsed()
                ->schema([
                    Forms\Components\Select::make('currency')
                        ->label('Order Currency')
                        ->options([
                            'USD' => 'USD — US Dollar',
                            'KHR' => 'KHR — Cambodian Riel',
                            'CNY' => 'CNY — Chinese Yuan',
                            'THB' => 'THB — Thai Baht',
                            'SGD' => 'SGD — Singapore Dollar',
                            'EUR' => 'EUR — Euro',
                            'GBP' => 'GBP — British Pound',
                        ])
                        ->default('USD'),

                    Forms\Components\TextInput::make('exchange_rate')
                        ->label('Exchange Rate to USD')
                        ->numeric()
                        ->default(1)
                        ->helperText('Leave as 1 if ordering in USD.'),

                    Forms\Components\TextInput::make('freight_cost')
                        ->label('Freight / Shipping Cost')
                        ->numeric()
                        ->prefix('$')
                        ->default(0),

                    Forms\Components\TextInput::make('customs_duty')
                        ->label('Customs Duty')
                        ->numeric()
                        ->prefix('$')
                        ->default(0),

                    Forms\Components\TextInput::make('total')
                        ->label('Total Order Value')
                        ->numeric()
                        ->prefix('$')
                        ->default(0)
                        ->helperText('Total value of goods only, before freight and customs.'),
                ])->columns(3),

            // ── Section 5: Notes ──────────────────────────────────────
            Forms\Components\Section::make('Notes')
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->label('Additional Notes')
                        ->placeholder('Any extra information about this order...')
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
                    ->label('Order No.')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),

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
                    ->label('Order Date')
                    ->date('d M Y')
                    ->sortable(),

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

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Deliver To')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('tracking_number')
                    ->label('Tracking')
                    ->placeholder('—')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Filter by Status')
                    ->options([
                        'draft'              => 'Draft',
                        'sent'               => 'Sent to Supplier',
                        'confirmed'          => 'Confirmed',
                        'shipped'            => 'Shipped',
                        'partially_received' => 'Partially Received',
                        'received'           => 'Fully Received',
                        'cancelled'          => 'Cancelled',
                    ]),

                Tables\Filters\SelectFilter::make('supplier')
                    ->relationship('supplier', 'name')
                    ->label('Filter by Supplier'),

                Tables\Filters\Filter::make('overdue')
                    ->label('Overdue Orders')
                    ->query(fn ($query) =>
                        $query->whereNotNull('expected_date')
                            ->where('expected_date', '<', now())
                            ->whereNotIn('status', ['received', 'cancelled'])
                    )
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Edit / Receive'),
                Tables\Actions\DeleteAction::make()
                    ->label('Delete'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No orders yet')
            ->emptyStateDescription('Create your first purchase order to start tracking what you are ordering.')
            ->emptyStateIcon('heroicon-o-shopping-cart');
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