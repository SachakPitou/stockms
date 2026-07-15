<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\LowStockTable;
use App\Filament\Widgets\RecentPurchaseOrders;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\StockMovementChart;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $title = 'MAT Telecom — Stock Management';
    protected static ?int $navigationSort = -1;

    public function getWidgets(): array
    {
        return [
            StatsOverview::class,
            StockMovementChart::class,
            LowStockTable::class,
            RecentPurchaseOrders::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return 1;
    }
}