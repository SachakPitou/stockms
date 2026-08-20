<?php

namespace App\Helpers;

class WarehouseHelper
{
    /**
     * Roles that see everything, unrestricted.
     */
    public static function seesAllWarehouses(): bool
    {
        return auth()->check() &&
            auth()->user()->hasAnyRole(['Admin', 'HR Staff', 'Verify Team', 'Approval Team', 'Approver']);
    }

    /**
     * Restrict a query to the logged-in user's own warehouse,
     * unless they're an Admin/HR role (who see everything).
     */
    public static function restrictToUserWarehouse($query, string $warehouseColumn = 'warehouse_id'): mixed
    {
        if (self::seesAllWarehouses()) {
            return $query;
        }

        $warehouseId = auth()->user()?->warehouse_id;

        if ($warehouseId) {
            $query->where($warehouseColumn, $warehouseId);
        } else {
            // No warehouse assigned and not an HR/Admin role — show nothing rather than everything.
            $query->whereRaw('1 = 0');
        }

        return $query;
    }
}