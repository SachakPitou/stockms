<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EquipmentUnitsRelationManager extends RelationManager
{
    protected static string $relationship = 'equipmentUnits';
    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->is_serialized;
    }
    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('serial_number')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),

            Forms\Components\Select::make('warehouse_id')
                ->relationship('warehouse', 'name')
                ->required(),

            Forms\Components\Select::make('condition')
                ->options([
                    'new'          => 'New',
                    'in_use'       => 'In Use',
                    'refurbished'  => 'Refurbished (Ready)',
                    'under_repair' => 'Under Repair',
                    'scrapped'     => 'Scrapped',
                ])
                ->default('new')
                ->required(),

            Forms\Components\DatePicker::make('purchase_date'),

            Forms\Components\Textarea::make('notes')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('serial_number')
            ->modifyQueryUsing(function ($query) {
                return \App\Helpers\WarehouseHelper::restrictToUserWarehouse($query);
            })
            ->columns([
                Tables\Columns\TextColumn::make('serial_number')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Location'),

                Tables\Columns\TextColumn::make('condition_label')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record) => $record->condition_color),

                Tables\Columns\TextColumn::make('currentCustomer.name')
                    ->label('Issued To')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('purchase_date')
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('condition')
                    ->options([
                        'new'          => 'New',
                        'in_use'       => 'In Use',
                        'refurbished'  => 'Refurbished (Ready)',
                        'under_repair' => 'Under Repair',
                        'scrapped'     => 'Scrapped',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),

                Tables\Actions\Action::make('bulkAdd')
                    ->label('Bulk Add Units')
                    ->icon('heroicon-o-queue-list')
                    ->color('primary')
                    ->visible(fn () => auth()->user()->hasAnyRole(['Admin', 'HR Approver', 'HR Staff']))
                    ->form([
                        Forms\Components\Select::make('warehouse_id')
                            ->relationship('warehouse', 'name')
                            ->required()
                            ->label('Warehouse')
                            ->live()
                            ->helperText(function (Forms\Get $get, $livewire) {
                                $warehouseId = $get('warehouse_id');
                                if (! $warehouseId) return null;

                                $pending = \App\Models\StockLevel::where('product_id', $livewire->getOwnerRecord()->id)
                                    ->where('warehouse_id', $warehouseId)
                                    ->value('quantity') ?? 0;

                                return "Unserialized stock available at this warehouse: {$pending}";
                            }),

                        Forms\Components\Select::make('condition')
                            ->options([
                                'new'         => 'New',
                                'refurbished' => 'Refurbished (Ready)',
                            ])
                            ->default('new')
                            ->required(),

                        Forms\Components\Textarea::make('serials')
                            ->label('Serial Numbers')
                            ->helperText('One serial number per line. Cannot exceed the unserialized stock quantity at the selected warehouse.')
                            ->rows(8)
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $product = $this->getOwnerRecord();

                        $lines = collect(explode("\n", $data['serials']))
                            ->map(fn ($s) => trim($s))
                            ->filter()
                            ->unique()
                            ->values();

                        if ($lines->isEmpty()) {
                            \Filament\Notifications\Notification::make()
                                ->title('No serial numbers entered')
                                ->warning()
                                ->send();
                            return;
                        }

                        $pending = \App\Models\StockLevel::where('product_id', $product->id)
                            ->where('warehouse_id', $data['warehouse_id'])
                            ->value('quantity') ?? 0;

                        if ($lines->count() > $pending) {
                            \Filament\Notifications\Notification::make()
                                ->title('Too many serial numbers')
                                ->body("Only {$pending} unserialized unit(s) available at this warehouse. You entered {$lines->count()}.")
                                ->danger()
                                ->send();
                            return;
                        }

                        $created = 0;
                        $skipped = [];

                        \Illuminate\Support\Facades\DB::transaction(function () use ($lines, $data, $product, &$created, &$skipped) {
                            foreach ($lines as $serial) {
                                if (\App\Models\EquipmentUnit::where('serial_number', $serial)->exists()) {
                                    $skipped[] = $serial;
                                    continue;
                                }

                                $product->equipmentUnits()->create([
                                    'warehouse_id'  => $data['warehouse_id'],
                                    'serial_number' => $serial,
                                    'condition'     => $data['condition'],
                                    'added_by'      => auth()->id(),
                                ]);
                                $created++;
                            }

                            if ($created > 0) {
                                \App\Models\StockLevel::where('product_id', $product->id)
                                    ->where('warehouse_id', $data['warehouse_id'])
                                    ->decrement('quantity', $created);
                            }
                        });

                        $message = "{$created} unit(s) added.";
                        if (count($skipped)) {
                            $message .= ' Skipped duplicates: ' . implode(', ', $skipped);
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Bulk Add Complete')
                            ->body($message)
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }
}
