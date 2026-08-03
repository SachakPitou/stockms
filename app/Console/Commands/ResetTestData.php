<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetTestData extends Command
{
    protected $signature = "app:reset-test-data";
    protected $description = "Truncate all transactional data, keeping users, roles, permissions, and warehouses";

    public function handle(): void
    {
        DB::statement("SET FOREIGN_KEY_CHECKS=0");

        $tables = [
            "stock_transfer_units",
            "stock_transfers",
            "stock_movements",
            "stock_levels",
            "equipment_issuances",
            "equipment_returns",
            "equipment_units",
            "purchase_request_items",
            "purchase_requests",
            "purchase_order_items",
            "purchase_orders",
            "products",
            "suppliers",
            "customers",
            "activity_log",
        ];

        foreach ($tables as $table) {
            DB::table($table)->truncate();
            $this->info("Truncated: {$table}");
        }

        DB::statement("SET FOREIGN_KEY_CHECKS=1");

        $this->info("Done. Users, roles, permissions, and warehouses were preserved.");
    }
}
