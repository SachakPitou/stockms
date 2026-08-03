<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Warehouse;
use App\Models\Supplier;
use App\Models\Category;
use App\Models\Product;
use App\Models\Customer;
use App\Models\StockLevel;

class SeedTestData extends Command
{
    protected $signature = "app:seed-test-data";
    protected $description = "Insert sample suppliers, products, stock, and customers for testing";

    public function handle(): void
    {
        $main   = Warehouse::where("is_default", true)->first() ?? Warehouse::first();
        $pp     = Warehouse::where("name", "like", "%Phnom Penh%")->first();
        $poipet = Warehouse::where("name", "like", "%Poipet%")->first();

        if (! $main || ! $pp || ! $poipet) {
            $this->error("Could not find all 3 warehouses. Check names match: Main / Phnom Penh / Poipet.");
            return;
        }

        // ── Supplier ─────────────────────────────────────────────
        $supplier = Supplier::create([
            "name"           => "Huawei Supply Co.",
            "country"        => "China",
            "address"        => "123 Tech Park Road, Shenzhen",
            "currency"       => "USD",
            "contact_name"   => "Li Wei",
            "contact_email"  => "liwei@huaweisupply.test",
            "contact_phone"  => "+86 123 4567 8900",
            "lead_time_days" => 14,
            "is_active"      => true,
        ]);
        $this->info("Created supplier: {$supplier->name}");

        // ── Category ─────────────────────────────────────────────
        $category = Category::firstOrCreate(
            ["slug" => "networking-equipment"],
            ["name" => "Networking Equipment", "slug" => "networking-equipment"]
        );
        // ── Non-serialized product (plain quantity) ─────────────
        $cable = Product::create([
            "sku"            => "CBL-001",
            "name"           => "Cat6 Ethernet Cable (box)",
            "category_id"    => $category->id,
            "supplier_id"    => $supplier->id,
            "unit"           => "box",
            "unit_cost"      => 25,
            "selling_price"  => 0,
            "reorder_point"  => 5,
            "reorder_qty"    => 20,
            "is_active"      => true,
            "is_serialized"  => false,
        ]);

        StockLevel::create(["product_id" => $cable->id, "warehouse_id" => $main->id, "quantity" => 15]);
        StockLevel::create(["product_id" => $cable->id, "warehouse_id" => $pp->id, "quantity" => 8]);
        StockLevel::create(["product_id" => $cable->id, "warehouse_id" => $poipet->id, "quantity" => 3]);
        $this->info("Created product: {$cable->name} (non-serialized, stock in all 3 warehouses)");

        // ── Serialized product (Router) ─────────────────────────
        $router = Product::create([
            "sku"            => "RTR-001",
            "name"           => "Huawei ONU HG8145V5",
            "category_id"    => $category->id,
            "supplier_id"    => $supplier->id,
            "unit"           => "pcs",
            "unit_cost"      => 45,
            "selling_price"  => 0,
            "is_active"      => true,
            "is_serialized"  => true,
        ]);

        // Simulate "6 received, not yet serialized" at Main
        StockLevel::create(["product_id" => $router->id, "warehouse_id" => $main->id, "quantity" => 6]);
        $this->info("Created product: {$router->name} (serialized, 6 unserialized units pending at Main)");

        // ── Customers ────────────────────────────────────────────
        Customer::create([
            "cid"    => "PP-0001",
            "name"   => "Sok Dara",
            "phone"  => "012 345 678",
            "area"   => "Phnom Penh",
            "status" => "active",
        ]);

        Customer::create([
            "cid"    => "PT-0001",
            "name"   => "Chan Vibol",
            "phone"  => "017 888 999",
            "area"   => "Poipet",
            "status" => "active",
        ]);

        $this->info("Created 2 test customers (1 Phnom Penh, 1 Poipet)");
        $this->info("Done. Test data ready.");
    }
}
