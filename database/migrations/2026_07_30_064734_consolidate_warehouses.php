<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Rename the 3 keepers ────────────────────────────────────
        DB::table('warehouses')->where('id', 1)->update([
            'name' => 'Main Warehouse',
            'is_default' => true,
        ]);
        DB::table('warehouses')->where('id', 3)->update([
            'name' => 'Phnom Penh Warehouse',
            'is_default' => false,
        ]);
        DB::table('warehouses')->where('id', 5)->update([
            'name' => 'Poipet Warehouse',
            'is_default' => false,
        ]);

        // ── Wipe test data tied to the warehouses being removed (ids 2 & 4) ──
        foreach ([2, 4] as $wid) {
            DB::table('stock_levels')->where('warehouse_id', $wid)->delete();
            DB::table('stock_movements')->where('warehouse_id', $wid)->delete();
            DB::table('equipment_units')->where('warehouse_id', $wid)->delete();
            DB::table('stock_transfers')->where('from_warehouse_id', $wid)->orWhere('to_warehouse_id', $wid)->delete();
        }

        // Purchase orders/requests referencing removed warehouses — repoint to Main (id 1)
        DB::table('purchase_orders')->whereIn('warehouse_id', [2, 4])->update(['warehouse_id' => 1]);
        DB::table('purchase_orders')->whereIn('destination_warehouse_id', [2, 4])->update(['destination_warehouse_id' => 1]);
        DB::table('purchase_requests')->whereIn('warehouse_id', [2, 4])->update(['warehouse_id' => 1]);

        // ── Delete the 2 extra warehouses ───────────────────────────
        DB::table('warehouses')->whereIn('id', [2, 4])->delete();
    }

    public function down(): void
    {
        // Data removal is not reversible — this migration is one-directional by design.
    }
};