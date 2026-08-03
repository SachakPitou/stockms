<?php

namespace App\Filament\Resources\EquipmentUnitResource\Pages;

use App\Filament\Resources\EquipmentUnitResource;
use App\Models\EquipmentUnit;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListEquipmentUnits extends ListRecords
{
    protected static string $resource = EquipmentUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Register New Unit'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Units'),

            'available' => Tab::make('Available')
                ->modifyQueryUsing(fn (Builder $query) =>
                    $query->whereIn('condition', ['new', 'refurbished'])
                )
                ->badge(EquipmentUnit::whereIn('condition', ['new', 'refurbished'])->count())
                ->badgeColor('success'),

            'new' => Tab::make('New')
                ->modifyQueryUsing(fn (Builder $query) =>
                    $query->where('condition', 'new')
                )
                ->badge(EquipmentUnit::where('condition', 'new')->count())
                ->badgeColor('success'),

            'refurbished' => Tab::make('Refurbished')
                ->modifyQueryUsing(fn (Builder $query) =>
                    $query->where('condition', 'refurbished')
                )
                ->badge(EquipmentUnit::where('condition', 'refurbished')->count())
                ->badgeColor('warning'),

            'in_use' => Tab::make('In Use')
                ->modifyQueryUsing(fn (Builder $query) =>
                    $query->where('condition', 'in_use')
                )
                ->badge(EquipmentUnit::where('condition', 'in_use')->count())
                ->badgeColor('info'),

            'under_repair' => Tab::make('Under Repair')
                ->modifyQueryUsing(fn (Builder $query) =>
                    $query->where('condition', 'under_repair')
                )
                ->badge(EquipmentUnit::where('condition', 'under_repair')->count())
                ->badgeColor('gray'),

            'scrapped' => Tab::make('Scrapped')
                ->modifyQueryUsing(fn (Builder $query) =>
                    $query->where('condition', 'scrapped')
                )
                ->badge(EquipmentUnit::where('condition', 'scrapped')->count())
                ->badgeColor('danger'),
        ];
    }
}
