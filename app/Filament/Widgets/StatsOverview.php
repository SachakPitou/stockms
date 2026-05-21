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
        $totalProducts  = Product::where('is_active', true)->count();
        $lowStockCount  = app(StockService::class)->getLowStockProducts()->count();
        $pendingOrders  = PurchaseOrder::whereIn('status', ['draft', 'sent', 'confirmed', 'shipped'])->count();

        // Total stock value = sum of (quantity * unit_cost) across all levels
        $stockValue = StockLevel::join('products', 'products.id', '=', 'stock_levels.product_id')
            ->selectRaw('SUM(stock_levels.quantity * products.unit_cost) as total')
            ->value('total') ?? 0;

        // Movement trend — compare this week vs last week
        $thisWeek = StockMovement::where('moved_at', '>=', now()->startOfWeek())->count();
        $lastWeek = StockMovement::whereBetween('moved_at', [
            now()->subWeek()->startOfWeek(),
            now()->subWeek()->endOfWeek(),
        ])->count();

        $movementTrend       = $lastWeek > 0 ? (($thisWeek - $lastWeek) / $lastWeek) * 100 : 0;
        $movementTrendColor  = $movementTrend >= 0 ? 'success' : 'danger';
        $movementTrendIcon   = $movementTrend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';

        return [
            Stat::make('Total Products', $totalProducts)
                ->description('Active products in system')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('primary'),

            Stat::make('Low Stock Alerts', $lowStockCount)
                ->description($lowStockCount > 0 ? 'Products need reordering' : 'All stock levels healthy')
                ->descriptionIcon($lowStockCount > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($lowStockCount > 0 ? 'danger' : 'success'),

            Stat::make('Pending Orders', $pendingOrders)
                ->description('Purchase orders in progress')
                ->descriptionIcon('heroicon-m-truck')
                ->color('warning'),

            Stat::make('Total Stock Value', '$' . number_format($stockValue, 2))
                ->description('Based on unit cost × quantity')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
        ];
    }
}