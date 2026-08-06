<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquipmentUnitResource\Pages;
use App\Models\Customer;
use App\Models\EquipmentUnit;
use App\Models\Product;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EquipmentUnitResource extends Resource
{
    protected static ?string $model = EquipmentUnit::class;
    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';
    protected static ?string $navigationGroup = 'Customers';
    protected static ?string $navigationLabel = 'Equipment Units';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Unit Details')
                ->schema([
                    Forms\Components\Select::make('product_id')
                        ->label('Product / Equipment Type')
                        ->options(Product::where('is_active', true)->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\Select::make('warehouse_id')
                        ->label('Stored At')
                        ->options(Warehouse::pluck('name', 'id'))
                        ->required(),

                    Forms\Components\TextInput::make('serial_number')
                        ->label('Serial Number')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->placeholder('e.g. SN-HW-001234'),

                    Forms\Components\Select::make('condition')
                        ->label('Condition')
                        ->options([
                            'new'          => '🟢 New',
                            'in_use'       => '🔵 In Use',
                            'refurbished'  => '🟡 Refurbished (Ready to reuse)',
                            'under_repair' => '🔧 Under Repair',
                            'scrapped'     => '❌ Scrapped',
                        ])
                        ->default('new')
                        ->required(),

                    Forms\Components\DatePicker::make('purchase_date')
                        ->label('Purchase Date')
                        ->displayFormat('d M Y'),

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
                Tables\Columns\TextColumn::make('serial_number')
                    ->label('Serial Number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Equipment Type')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('condition')
                    ->label('Condition')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'new'          => '🟢 New',
                        'in_use'       => '🔵 In Use',
                        'refurbished'  => '🟡 Refurbished',
                        'under_repair' => '🔧 Under Repair',
                        'scrapped'     => '❌ Scrapped',
                        default        => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match($state) {
                        'new'          => 'success',
                        'in_use'       => 'info',
                        'refurbished'  => 'warning',
                        'under_repair' => 'gray',
                        'scrapped'     => 'danger',
                        default        => 'gray',
                    }),

                Tables\Columns\TextColumn::make('currentCustomer.name')
                    ->label('With Customer')
                    ->placeholder('— In Stock —')
                    ->description(fn ($record) =>
                        $record->currentCustomer
                            ? 'CID: ' . $record->currentCustomer->cid
                            : null
                    ),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Location')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('purchase_date')
                    ->label('Purchased')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('condition')
                    ->options([
                        'new'          => '🟢 New',
                        'in_use'       => '🔵 In Use',
                        'refurbished'  => '🟡 Refurbished',
                        'under_repair' => '🔧 Under Repair',
                        'scrapped'     => '❌ Scrapped',
                    ]),

                Tables\Filters\SelectFilter::make('product')
                    ->relationship('product', 'name')
                    ->label('Filter by Product'),

                Tables\Filters\SelectFilter::make('warehouse')
                    ->relationship('warehouse', 'name')
                    ->label('Filter by Location'),

                Tables\Filters\Filter::make('available')
                    ->label('Available Only (New + Refurbished)')
                    ->query(fn ($query) =>
                        $query->whereIn('condition', ['new', 'refurbished'])
                    )
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('updateCondition')
                    ->label('Update Condition')
                    ->icon('heroicon-m-pencil-square')
                    ->color('warning')
                    ->form([
                        Forms\Components\Select::make('condition')
                            ->label('New Condition')
                            ->options([
                                'new'          => '🟢 New',
                                'refurbished'  => '🟡 Refurbished (Ready to reuse)',
                                'under_repair' => '🔧 Under Repair',
                                'scrapped'     => '❌ Scrapped',
                            ])
                            ->required(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Reason for change')
                            ->rows(2),
                    ])
                    ->action(function (EquipmentUnit $record, array $data) {
                        $record->update([
                            'condition' => $data['condition'],
                            'notes'     => $data['notes'] ?? $record->notes,
                        ]);

                        // If scrapped or under repair,
                        // clear customer association
                        if (in_array($data['condition'], ['scrapped', 'under_repair'])) {
                            $record->update(['current_customer_id' => null]);
                        }

                        Notification::make()
                            ->title('Condition updated')
                            ->body("Unit {$record->serial_number} is now: {$data['condition']}")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulkUpdateCondition')
                        ->label('Update Condition')
                        ->icon('heroicon-m-pencil-square')
                        ->form([
                            Forms\Components\Select::make('condition')
                                ->label('Set Condition For Selected')
                                ->options([
                                    'new'          => '🟢 New',
                                    'refurbished'  => '🟡 Refurbished',
                                    'under_repair' => '🔧 Under Repair',
                                    'scrapped'     => '❌ Scrapped',
                                ])
                                ->required(),
                        ])
                        ->action(function ($records, array $data) {
                            $records->each->update(['condition' => $data['condition']]);

                            Notification::make()
                                ->title('Conditions updated')
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListEquipmentUnits::route('/'),
            'create' => Pages\CreateEquipmentUnit::route('/create'),
            'edit'   => Pages\EditEquipmentUnit::route('/{record}/edit'),
            'view'   => Pages\ViewEquipmentUnit::route('/{record}'),
        ];
    }
    public static function canCreate(): bool
    {
        return false;
    }
    public static function canEdit($record): bool
    {
        // Everyone with warehouse access can fix a typo'd serial;
        // creating new ones stays HR/Admin-only above.
        return true;
    }
}
