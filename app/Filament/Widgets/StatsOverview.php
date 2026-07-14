<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Services\StockService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $totalProducts = Product::where('is_active', true)->count();

        $lowStockCount = app(StockService::class)->getLowStockProducts()->count();

        $pendingOrders = PurchaseOrder::whereIn('status', [
            'draft', 'sent', 'confirmed', 'shipped'
        ])->count();

        $stockValue = StockLevel::join('products', 'products.id', '=', 'stock_levels.product_id')
            ->selectRaw('SUM(stock_levels.quantity * products.unit_cost) as total')
            ->value('total') ?? 0;

        $todayMovements = StockMovement::whereDate('moved_at', today())->count();

        return [
            Stat::make('Total Products', $totalProducts)
                ->description('Active products being tracked')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('primary'),

            Stat::make('Running Low', $lowStockCount)
                ->description(
                    $lowStockCount > 0
                        ? 'Products need to be restocked'
                        : 'All products have sufficient stock'
                )
                ->descriptionIcon(
                    $lowStockCount > 0
                        ? 'heroicon-m-exclamation-triangle'
                        : 'heroicon-m-check-circle'
                )
                ->color($lowStockCount > 0 ? 'danger' : 'success'),

            Stat::make('Orders in Progress', $pendingOrders)
                ->description('Orders not yet fully received')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('warning'),

            Stat::make('Stock Movements Today', $todayMovements)
                ->description('Items added or removed today')
                ->descriptionIcon('heroicon-m-arrows-right-left')
                ->color('info'),
        ];
    }
}