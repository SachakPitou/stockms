<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Product Details')
                ->schema([
                    Infolists\Components\TextEntry::make('sku')
                        ->label('SKU')
                        ->weight('bold'),
                    Infolists\Components\TextEntry::make('name'),
                    Infolists\Components\TextEntry::make('category.name')
                        ->badge()
                        ->color('gray'),
                    Infolists\Components\TextEntry::make('supplier.name'),
                    Infolists\Components\TextEntry::make('unit_cost')
                        ->money('USD'),
                    Infolists\Components\TextEntry::make('selling_price')
                        ->money('USD'),
                    Infolists\Components\TextEntry::make('reorder_point'),
                    Infolists\Components\TextEntry::make('reorder_qty'),
                ])->columns(2),

            // ── HR/Admin only — full breakdown across all warehouses ──────
            Infolists\Components\Section::make('Stock Levels by Warehouse')
                ->visible(fn () => \App\Helpers\WarehouseHelper::seesAllWarehouses())
                ->schema([
                    Infolists\Components\RepeatableEntry::make('stockLevels')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('warehouse.name')
                                ->label('Warehouse')
                                ->badge()
                                ->color('info'),
                            Infolists\Components\TextEntry::make('quantity')
                                ->label('In Stock')
                                ->weight('bold')
                                ->color(fn ($record) =>
                                    $record->quantity <= 0 ? 'danger' : 'success'
                                ),
                            Infolists\Components\TextEntry::make('reserved')
                                ->label('Reserved')
                                ->color('warning'),
                            Infolists\Components\TextEntry::make('available')
                                ->label('Available')
                                ->getStateUsing(fn ($record) =>
                                    $record->quantity - $record->reserved
                                )
                                ->weight('bold'),
                        ])->columns(4),
                ]),

            // ── Poipet/PP tech — only their own warehouse's number ─────────
            Infolists\Components\Section::make('Stock in Your Warehouse')
                ->visible(fn () => ! \App\Helpers\WarehouseHelper::seesAllWarehouses())
                ->schema([
                    Infolists\Components\TextEntry::make('your_stock')
                        ->label('')
                        ->getStateUsing(function ($record) {
                            $warehouseId = auth()->user()?->warehouse_id;
                            $level = $record->stockLevels->firstWhere('warehouse_id', $warehouseId);

                            if (! $level) {
                                return 'No stock recorded for your warehouse.';
                            }

                            $available = $level->quantity - $level->reserved;
                            return "{$level->quantity} {$record->unit} in stock" .
                                ($level->reserved > 0 ? " ({$level->reserved} reserved, {$available} available)" : '');
                        })
                        ->weight('bold')
                        ->color(fn ($record) => match(true) {
                            optional($record->stockLevels->firstWhere('warehouse_id', auth()->user()?->warehouse_id))->quantity <= 0 => 'danger',
                            default => 'success',
                        }),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\EditAction::make(),
        ];
    }
}