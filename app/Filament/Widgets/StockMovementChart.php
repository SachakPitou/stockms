<?php

namespace App\Filament\Widgets;

use App\Models\StockMovement;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class StockMovementChart extends ChartWidget
{
    protected static ?string $heading = 'Stock Movements — Last 30 Days';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        // Get last 30 days of movements grouped by date and type
        $receipts = StockMovement::select(
                DB::raw('DATE(moved_at) as date'),
                DB::raw('SUM(ABS(quantity)) as total')
            )
            ->where('type', 'receipt')
            ->where('moved_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->toArray();

        $issues = StockMovement::select(
                DB::raw('DATE(moved_at) as date'),
                DB::raw('SUM(ABS(quantity)) as total')
            )
            ->whereIn('type', ['issue', 'transfer'])
            ->where('moved_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->toArray();

        // Build a complete 30-day date range so empty days show as 0
        $labels        = [];
        $receiptData   = [];
        $issueData     = [];

        for ($i = 29; $i >= 0; $i--) {
            $date        = now()->subDays($i)->format('Y-m-d');
            $label       = now()->subDays($i)->format('d M');
            $labels[]    = $label;
            $receiptData[] = $receipts[$date] ?? 0;
            $issueData[]   = $issues[$date]   ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label'           => 'Stock In (Receipts)',
                    'data'            => $receiptData,
                    'borderColor'     => '#22c55e',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'fill'            => true,
                    'tension'         => 0.4,
                ],
                [
                    'label'           => 'Stock Out (Issues)',
                    'data'            => $issueData,
                    'borderColor'     => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'fill'            => true,
                    'tension'         => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}