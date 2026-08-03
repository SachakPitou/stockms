<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Expand the ENUM to temporarily accept BOTH old and new status values
        DB::statement("ALTER TABLE purchase_orders MODIFY COLUMN status 
            ENUM('draft','sent','confirmed','shipped','partially_received','received','cancelled','submitted','verified','approved','ordered') 
            DEFAULT 'draft'");

        // 2. Safely map existing records to the new values
        DB::table('purchase_orders')
            ->whereIn('status', ['sent', 'confirmed'])
            ->update(['status' => 'submitted']);

        DB::table('purchase_orders')
            ->where('status', 'partially_received')
            ->update(['status' => 'received']);

        // 3. Trim the ENUM to contain strictly the new values
        DB::statement("ALTER TABLE purchase_orders MODIFY COLUMN status 
            ENUM('draft','submitted','verified','approved','ordered','shipped','received','cancelled') 
            DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Expand the ENUM to allow both again for rollback
        DB::statement("ALTER TABLE purchase_orders MODIFY COLUMN status 
            ENUM('draft','sent','confirmed','shipped','partially_received','received','cancelled','submitted','verified','approved','ordered') 
            DEFAULT 'draft'");

        // 2. Map new statuses back to old equivalents
        DB::table('purchase_orders')
            ->whereIn('status', ['submitted', 'verified', 'approved', 'ordered'])
            ->update(['status' => 'sent']);

        // 3. Revert column definition back strictly to original ENUM values
        DB::statement("ALTER TABLE purchase_orders MODIFY COLUMN status 
            ENUM('draft','sent','confirmed','shipped','partially_received','received','cancelled') 
            DEFAULT 'draft'");
    }
};