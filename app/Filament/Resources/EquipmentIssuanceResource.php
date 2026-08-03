<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquipmentIssuanceResource\Pages;
use App\Models\Customer;
use App\Models\EquipmentIssuance;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\StockService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EquipmentIssuanceResource extends Resource
{
    protected static ?string $model = EquipmentIssuance::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationGroup = 'Customers';
    protected static ?string $navigationLabel = 'Issue Equipment';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Equipment Issuance')
                ->description('Issue equipment to a customer. Stock will be deducted automatically.')
                ->schema([
                    Forms\Components\Select::make('customer_id')
                        ->label('Customer (CID)')
                        ->options(
                            Customer::where('status', 'active')
                                ->get()
                                ->mapWithKeys(fn ($c) => [$c->id => "{$c->cid} — {$c->name}"])
                        )
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpanFull(),

                    Forms\Components\Select::make('product_id')
                        ->label('Equipment Type')
                        ->options(Product::where('is_active', true)->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('units', [])),

                    Forms\Components\Select::make('warehouse_id')
                        ->label('Issue From Warehouse')
                        ->options(Warehouse::pluck('name', 'id'))
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('units', [])),

                    Forms\Components\TextInput::make('quantity')
                        ->label('Quantity')
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->required()
                        ->live()
                        ->rule(function (Forms\Get $get) {
                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                $productId = $get('product_id');
                                $warehouseId = $get('warehouse_id');
                                if (! $productId) return;

                                $product = Product::find($productId);
                                if (! $product?->is_serialized) return;

                                $available = \App\Models\EquipmentUnit::where('product_id', $productId)
                                    ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
                                    ->whereIn('condition', ['new', 'refurbished'])
                                    ->count();

                                if ((int) $value > $available) {
                                    $fail("Not enough stock — only {$available} unit(s) available.");
                                }
                            };
                        })
                        ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                            $product = Product::find($get('product_id'));
                            if (! $product?->is_serialized) return;

                            $qty = max(1, (int) $state);
                            $current = $get('units') ?? [];
                            $current = array_values($current);

                            if ($qty > count($current)) {
                                for ($i = count($current); $i < $qty; $i++) {
                                    $current[] = ['equipment_unit_id' => null];
                                }
                            } elseif ($qty < count($current)) {
                                $current = array_slice($current, 0, $qty);
                            }

                            $set('units', $current);
                        }),

                    // ── Serialized products: pick specific units ────────────────
                    Forms\Components\Repeater::make('units')
                        ->label('Select Serial Number(s)')
                        ->schema([
                            Forms\Components\Select::make('equipment_unit_id')
                                ->label('Serial Number')
                                ->options(function (Forms\Get $get) {
                                    $productId   = $get('../../product_id');
                                    $warehouseId = $get('../../warehouse_id');
                                    if (! $productId) return [];

                                    $siblings = collect($get('../../units') ?? [])
                                        ->pluck('equipment_unit_id')
                                        ->filter()
                                        ->reject(fn ($id) => $id == $get('equipment_unit_id'))
                                        ->all();

                                    return \App\Models\EquipmentUnit::where('product_id', $productId)
                                        ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
                                        ->whereIn('condition', ['new', 'refurbished'])
                                        ->whereNotIn('id', $siblings)
                                        ->get()
                                        ->mapWithKeys(fn ($u) => [$u->id => "{$u->serial_number} — {$u->condition}"]);
                                })
                                ->searchable()
                                ->required()
                                ->live(),
                        ])
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->columnSpanFull()
                        ->visible(fn (Forms\Get $get) => Product::find($get('product_id'))?->is_serialized ?? false),

                    // ── Non-serialized products: plain optional serial text ─────
                    Forms\Components\TextInput::make('serial_number')
                        ->label('Serial Number')
                        ->placeholder('e.g. SN-HW-00123')
                        ->helperText('Optional — enter if tracking individual device')
                        ->visible(fn (Forms\Get $get) => ! (Product::find($get('product_id'))?->is_serialized ?? false)),

                    Forms\Components\DatePicker::make('issued_date')
                        ->label('Issue Date')
                        ->default(now())
                        ->required()
                        ->displayFormat('d M Y'),

                    Forms\Components\DatePicker::make('expected_return_date')
                        ->label('Expected Return Date')
                        ->displayFormat('d M Y')
                        ->helperText('Optional — leave blank if no return expected'),

                    Forms\Components\Textarea::make('notes')
                        ->label('Notes')
                        ->rows(2)
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(function ($query) {
                return \App\Helpers\WarehouseHelper::restrictToUserWarehouse($query);
            })
            ->columns([
                Tables\Columns\TextColumn::make('customer.cid')
                    ->label('CID')
                    ->searchable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Equipment')
                    ->searchable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qty'),

                Tables\Columns\TextColumn::make('serial_number')
                    ->label('Serial No.')
                    ->placeholder('—')
                    ->copyable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'active'   => 'warning',
                        'returned' => 'success',
                        'lost'     => 'danger',
                        'damaged'  => 'danger',
                        default    => 'gray',
                    }),

                Tables\Columns\TextColumn::make('issued_date')
                    ->label('Issued')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expected_return_date')
                    ->label('Return By')
                    ->date('d M Y')
                    ->placeholder('No return')
                    ->color(fn ($record) =>
                        $record->expected_return_date &&
                        $record->expected_return_date->isPast() &&
                        $record->status === 'active'
                            ? 'danger' : null
                    ),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active'   => 'Active (out with customer)',
                        'returned' => 'Returned',
                        'lost'     => 'Lost',
                        'damaged'  => 'Damaged',
                    ]),

                Tables\Filters\Filter::make('overdue')
                    ->label('Overdue Returns')
                    ->query(fn ($query) =>
                        $query->where('status', 'active')
                            ->whereNotNull('expected_return_date')
                            ->where('expected_return_date', '<', now())
                    )
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('returnEquipment')
                    ->label('Process Return')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (EquipmentIssuance $record) => $record->status === 'active')
                    ->form([
                        Forms\Components\DatePicker::make('return_date')
                            ->label('Return Date')
                            ->default(now())
                            ->required()
                            ->displayFormat('d M Y'),

                        Forms\Components\Select::make('condition')
                            ->label('Equipment Condition')
                            ->options([
                                'good'         => '✅ Good — can be reused immediately',
                                'needs_repair' => '🔧 Needs Repair — send to technician',
                                'scrap'        => '❌ Scrap — cannot be reused',
                            ])
                            ->required(),

                        Forms\Components\Select::make('action')
                            ->label('What to do with this equipment?')
                            ->options([
                                'restock' => 'Put back into stock (reuse for next customer)',
                                'repair'  => 'Send for repair (remove from stock)',
                                'scrap'   => 'Scrap (write off)',
                            ])
                            ->required()
                            ->helperText('If you select "Put back into stock" the quantity will be added back automatically.'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2),
                    ])
                    ->action(function (EquipmentIssuance $record, array $data) {
                        app(StockService::class)->returnFromCustomer(
                            issuanceId: $record->id,
                            condition:  $data['condition'],
                            action:     $data['action'],
                            returnDate: $data['return_date'],
                            notes:      $data['notes'] ?? null,
                        );

                        Notification::make()
                            ->title('Equipment return processed')
                            ->body(
                                $data['action'] === 'restock'
                                    ? 'Equipment has been returned to stock.'
                                    : 'Equipment has been recorded as ' . $data['action'] . '.'
                            )
                            ->success()
                            ->send();
                    }),

                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListEquipmentIssuances::route('/'),
            'create' => Pages\CreateEquipmentIssuance::route('/create'),
            'view'   => Pages\ViewEquipmentIssuance::route('/{record}'),
        ];
    }
}
