<?php

namespace App\Filament\Resources\EquipmentUnitResource\Pages;

use App\Filament\Resources\EquipmentUnitResource;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewEquipmentUnit extends ViewRecord
{
    protected static string $resource = EquipmentUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\EditAction::make()];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Unit Details')
                ->schema([
                    Infolists\Components\TextEntry::make('serial_number')
                        ->label('Serial Number')
                        ->weight('bold')
                        ->copyable(),

                    Infolists\Components\TextEntry::make('product.name')
                        ->label('Equipment Type'),

                    Infolists\Components\TextEntry::make('condition')
                        ->label('Current Condition')
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

                    Infolists\Components\TextEntry::make('warehouse.name')
                        ->label('Location'),

                    Infolists\Components\TextEntry::make('currentCustomer.name')
                        ->label('Current Customer')
                        ->placeholder('Not with any customer'),

                    Infolists\Components\TextEntry::make('currentCustomer.cid')
                        ->label('Customer CID')
                        ->placeholder('—'),

                    Infolists\Components\TextEntry::make('purchase_date')
                        ->label('Purchase Date')
                        ->date('d M Y')
                        ->placeholder('Not recorded'),

                    Infolists\Components\TextEntry::make('notes')
                        ->label('Notes')
                        ->placeholder('No notes')
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }
}
