<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockTransferResource\Pages;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\StockService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StockTransferResource extends Resource
{
    protected static ?string $model = StockTransfer::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'Stock';
    protected static ?string $navigationLabel = 'Stock Transfers';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Transfer Details')
                ->schema([
                    Forms\Components\Select::make('product_id')
                        ->label('Product to Transfer')
                        ->options(Product::where('is_active', true)->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->columnSpanFull()
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('equipment_unit_ids', [])),

                    Forms\Components\Select::make('from_warehouse_id')
                        ->label('Transfer From')
                        ->options(Warehouse::pluck('name', 'id'))
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('equipment_unit_ids', [])),

                    Forms\Components\Select::make('to_warehouse_id')
                        ->label('Transfer To')
                        ->options(Warehouse::pluck('name', 'id'))
                        ->required()
                        ->different('from_warehouse_id'),

                    // ── Non-serialized: plain quantity ──────────────────────
                    Forms\Components\TextInput::make('quantity')
                        ->label('Quantity to Transfer')
                        ->numeric()
                        ->minValue(1)
                        ->required()
                        ->visible(fn (Forms\Get $get) => ! (Product::find($get('product_id'))?->is_serialized ?? false)),

                    // ── Serialized: pick specific units ─────────────────────
                    Forms\Components\CheckboxList::make('equipment_unit_ids')
                        ->label('Select Serial Number(s) to Transfer')
                        ->options(function (Forms\Get $get) {
                            $productId = $get('product_id');
                            $warehouseId = $get('from_warehouse_id');
                            if (! $productId || ! $warehouseId) return [];

                            return \App\Models\EquipmentUnit::where('product_id', $productId)
                                ->where('warehouse_id', $warehouseId)
                                ->whereIn('condition', ['new', 'refurbished'])
                                ->get()
                                ->mapWithKeys(fn ($u) => [$u->id => "{$u->serial_number} — {$u->condition}"]);
                        })
                        ->required()
                        ->columns(2)
                        ->columnSpanFull()
                        ->helperText('Only available units (New or Refurbished) currently at the source warehouse are shown.')
                        ->visible(fn (Forms\Get $get) => Product::find($get('product_id'))?->is_serialized ?? false),

                    Forms\Components\TextInput::make('reason')
                        ->label('Reason for Transfer')
                        ->placeholder('e.g. Poipet branch needs stock')
                        ->required()
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('notes')
                        ->label('Additional Notes')
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
                if (\App\Helpers\WarehouseHelper::seesAllWarehouses()) {
                    return $query;
                }
                $warehouseId = auth()->user()?->warehouse_id;
                if ($warehouseId) {
                    $query->where(function ($q) use ($warehouseId) {
                        $q->where('from_warehouse_id', $warehouseId)
                        ->orWhere('to_warehouse_id', $warehouseId);
                    });
                }
                return $query;
            })
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('fromWarehouse.name')
                    ->label('From')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('toWarehouse.name')
                    ->label('To')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qty')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'pending'            => 'warning',
                        'approved'           => 'info',
                        'completed'          => 'success',
                        'rejected'           => 'danger',
                        'confirmed_arrived'  => 'success',
                        default              => 'gray',
                    }),

                Tables\Columns\TextColumn::make('requestedBy.name')
                    ->label('Requested By'),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(30),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending'   => 'Pending',
                        'approved'  => 'Approved',
                        'completed' => 'Completed',
                        'rejected'  => 'Rejected',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve & Transfer')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->visible(fn (StockTransfer $record) =>
                        $record->status === 'pending' &&
                        auth()->user()->hasAnyRole(['Admin', 'HR Staff', 'Approval Team', 'Approver'])
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Approve Stock Transfer')
                    ->modalDescription(fn (StockTransfer $record) =>
                        "Transfer {$record->quantity} units of {$record->product->name} from {$record->fromWarehouse->name} to {$record->toWarehouse->name}?"
                    )
                    ->action(function (StockTransfer $record) {
                        app(StockService::class)->completeTransfer($record);

                        Notification::make()
                            ->title('Transfer completed')
                            ->body("Stock transferred successfully.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->visible(fn (StockTransfer $record) =>
                        $record->status === 'pending' &&
                        auth()->user()->hasAnyRole(['Admin', 'HR Staff', 'Approval Team', 'Approver'])
                    )
                    ->requiresConfirmation()
                    ->action(function (StockTransfer $record) {
                        $record->update(['status' => 'rejected']);

                        Notification::make()
                            ->title('Transfer rejected')
                            ->danger()
                            ->send();
                    }),

                Tables\Actions\Action::make('confirmArrival')
                    ->label('Confirm Arrival')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->visible(fn (StockTransfer $record) =>
                        $record->status === 'completed' &&
                        auth()->user()->hasAnyRole([
                            'Technical Team PP',
                            'Technical Team Poipet',
                            'Admin',
                            'HR Staff',
                            'Approval Team',
                        ])
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Stock Arrival')
                    ->modalDescription('Confirm that the stock has physically arrived at your warehouse.')
                    ->action(function (StockTransfer $record) {
                        $record->update(['status' => 'confirmed_arrived']);

                        Notification::make()
                            ->title('Arrival confirmed')
                            ->body('Stock arrival has been recorded.')
                            ->success()
                            ->send();
                    }),
            ]);
            
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListStockTransfers::route('/'),
            'create' => Pages\CreateStockTransfer::route('/create'),
            'view'   => Pages\ViewStockTransfer::route('/{record}'),
        ];
    }
}
