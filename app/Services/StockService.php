<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\StockTransfer;
use App\Models\EquipmentIssuance;

class StockService
{
    /**
     * Adjust stock for a product in a warehouse.
     * Type drives direction — issue/transfer = out, everything else = in.
     */
    public function adjust(
        int    $productId,
        int    $warehouseId,
        int    $quantity,
        string $type,
        string $reference = null,
        string $notes = null,
    ): StockMovement {
        return DB::transaction(function () use (
            $productId, $warehouseId, $quantity, $type, $reference, $notes
        ) {
            // Ensure stock level record exists
            StockLevel::firstOrCreate(
                [
                    'product_id'   => $productId,
                    'warehouse_id' => $warehouseId,
                ],
                [
                    'quantity' => 0,
                    'reserved' => 0,
                ]
            );

            // Get current quantity with a lock
            $level = StockLevel::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->first();

            $qtyBefore  = (int) $level->quantity;
            $isOutbound = in_array($type, ['issue', 'transfer']);
            $change     = $isOutbound ? -abs($quantity) : abs($quantity);
            $qtyAfter   = max(0, $qtyBefore + $change);

            // Use direct DB update to bypass any model issues
            DB::table('stock_levels')
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->update([
                    'quantity'   => $qtyAfter,
                    'updated_at' => now(),
                ]);

            // Write movement record
            $movement = StockMovement::create([
                'product_id'      => $productId,
                'warehouse_id'    => $warehouseId,
                'user_id'         => auth()->id(),
                'type'            => $type,
                'quantity'        => $change,
                'quantity_before' => $qtyBefore,
                'quantity_after'  => $qtyAfter,
                'reference'       => $reference,
                'notes'           => $notes,
                'moved_at'        => now(),
            ]);

            $this->checkLowStock($productId, $qtyAfter);

            return $movement;
        });
    }

    /**
     * Receive items from a purchase order (partial or full).
     * Automatically updates PO status.
     */
    /**
     * Receive items from a purchase order (partial or full).
     * $receivedItems = [itemId => ['qty' => int, 'serials' => array<string>|null]]
     */
    public function receivePurchaseOrder(PurchaseOrder $po, array $receivedQtys): void
    {
        \Log::info('receivePurchaseOrder called', [
            'po_id'        => $po->id,
            'po_number'    => $po->po_number,
            'warehouse_id' => $po->warehouse_id,
            'receivedQtys' => $receivedQtys,
        ]);

        DB::transaction(function () use ($po, $receivedQtys) {
            foreach ($receivedQtys as $itemId => $qty) {
                \Log::info('Processing item', ['itemId' => $itemId, 'qty' => $qty]);

                if ((int) $qty <= 0) {
                    \Log::info('Skipping item - qty <= 0');
                    continue;
                }

                $item = $po->items()->find($itemId);

                if (!$item) {
                    \Log::error('Item not found', ['itemId' => $itemId]);
                    continue;
                }

                $allowedQty = $item->qty_ordered - $item->qty_received;
                $qty        = min((int) $qty, $allowedQty);

                \Log::info('Adjusting stock', [
                    'product_id'   => $item->product_id,
                    'warehouse_id' => $po->warehouse_id,
                    'qty'          => $qty,
                ]);

                $item->qty_received += $qty;
                $item->save();

                $this->adjust(
                    productId:   $item->product_id,
                    warehouseId: $po->warehouse_id,
                    quantity:    $qty,
                    type:        'receipt',
                    reference:   $po->po_number,
                    notes:       "Received from PO #{$po->po_number}",
                );
            }

            $po->load('items');
            $totalOrdered  = $po->items->sum('qty_ordered');
            $totalReceived = $po->items->sum('qty_received');

            $status = $totalReceived >= $totalOrdered ? 'received' : 'partially_received';

            $po->update([
                'status'        => $status,
                'received_date' => $status === 'received' ? now() : $po->received_date,
            ]);
                    });
    }
    /**
     * Transfer stock from one warehouse to another.
     */
    public function transfer(
        int    $productId,
        int    $fromWarehouseId,
        int    $toWarehouseId,
        int    $quantity,
        string $notes = null,
    ): void {
        DB::transaction(function () use (
            $productId, $fromWarehouseId, $toWarehouseId, $quantity, $notes
        ) {
            $this->adjust(
                productId:   $productId,
                warehouseId: $fromWarehouseId,
                quantity:    $quantity,
                type:        'transfer',
                notes:       "Transfer out to warehouse #{$toWarehouseId}. {$notes}",
            );

            $this->adjust(
                productId:   $productId,
                warehouseId: $toWarehouseId,
                quantity:    $quantity,
                type:        'receipt',
                notes:       "Transfer in from warehouse #{$fromWarehouseId}. {$notes}",
            );
        });
    }
        /**
     * Complete a stock transfer request — deduct from source, add to destination.
     */
    public function completeTransfer(StockTransfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            $product = $transfer->product;

            if ($product->is_serialized) {
                $unitIds = $transfer->equipmentUnits()->pluck('equipment_units.id');

                \App\Models\EquipmentUnit::whereIn('id', $unitIds)
                    ->update(['warehouse_id' => $transfer->to_warehouse_id]);
            } else {
                $this->adjust(
                    productId:   $transfer->product_id,
                    warehouseId: $transfer->from_warehouse_id,
                    quantity:    $transfer->quantity,
                    type:        'transfer',
                    reference:   "TRF-{$transfer->id}",
                    notes:       "Transfer out to {$transfer->toWarehouse->name}",
                );

                $this->adjust(
                    productId:   $transfer->product_id,
                    warehouseId: $transfer->to_warehouse_id,
                    quantity:    $transfer->quantity,
                    type:        'receipt',
                    reference:   "TRF-{$transfer->id}",
                    notes:       "Transfer in from {$transfer->fromWarehouse->name}",
                );
            }

            $transfer->update([
                'status'         => 'completed',
                'approved_by'    => auth()->id(),
                'transferred_at' => now(),
            ]);
        });
    }
    /**
     * Calculate landed cost per unit for an overseas PO.
     * Freight + customs duty distributed proportionally by line value.
     */
    public function calculateLandedCost(PurchaseOrder $po): array
    {
        $items    = $po->items()->with('product')->get();
        $subtotal = $items->sum(fn($i) => $i->qty_ordered * $i->unit_price);
        $overhead = $po->freight_cost + $po->customs_duty;

        return $items->mapWithKeys(function ($item) use ($subtotal, $overhead, $po) {
            $lineTotal      = $item->qty_ordered * $item->unit_price;
            $share          = $subtotal > 0 ? $lineTotal / $subtotal : 0;
            $allocatedCost  = $overhead * $share;
            $totalCost      = ($lineTotal + $allocatedCost) * $po->exchange_rate;
            $unitLandedCost = $item->qty_ordered > 0 ? $totalCost / $item->qty_ordered : 0;

            return [$item->id => [
                'product'            => $item->product->name,
                'line_total'         => round($lineTotal, 2),
                'allocated_overhead' => round($allocatedCost, 2),
                'landed_total'       => round($totalCost, 2),
                'landed_unit_cost'   => round($unitLandedCost, 4),
            ]];
        })->all();
    }

    /**
     * Return all active products currently below their reorder point.
     */
    public function getLowStockProducts(): \Illuminate\Database\Eloquent\Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->whereHas('stockLevels', function ($q) {
                $q->whereRaw('stock_levels.quantity <= products.reorder_point');
            })
            ->with(['stockLevels.warehouse', 'supplier'])
            ->get();
    }
    /**
     * Issue equipment to a customer — deducts from stock.
     */
    public function issueToCustomer(
        int    $customerId,
        int    $productId,
        int    $warehouseId,
        int    $quantity,
        string $issuedDate,
        string $serialNumber = null,
        string $notes = null,
    ): EquipmentIssuance {
        return DB::transaction(function () use (
            $customerId, $productId, $warehouseId,
            $quantity, $issuedDate, $serialNumber, $notes
        ) {
            // Deduct from stock
            $this->adjust(
                productId:   $productId,
                warehouseId: $warehouseId,
                quantity:    $quantity,
                type:        'issue',
                reference:   "CID-{$customerId}",
                notes:       "Issued to customer ID {$customerId}",
            );

            // Record the issuance
            return \App\Models\EquipmentIssuance::create([
                'customer_id'   => $customerId,
                'product_id'    => $productId,
                'warehouse_id'  => $warehouseId,
                'issued_by'     => auth()->id(),
                'quantity'      => $quantity,
                'serial_number' => $serialNumber,
                'issued_date'   => $issuedDate,
                'status'        => 'active',
                'notes'         => $notes,
            ]);
        });
    }
    /**
     * Issue one or more specific serialized units to a customer.
     * Does NOT touch stock_levels — availability is derived from equipment_units.
     */
    public function issueUnitsToCustomer(
        int    $customerId,
        int    $productId,
        int    $warehouseId,
        array  $equipmentUnitIds,
        string $issuedDate,
        string $expectedReturnDate = null,
        string $notes = null,
    ): \Illuminate\Support\Collection {
        return DB::transaction(function () use (
            $customerId, $productId, $warehouseId,
            $equipmentUnitIds, $issuedDate, $expectedReturnDate, $notes
        ) {
            $issuances = collect();

            foreach ($equipmentUnitIds as $unitId) {
                $unit = \App\Models\EquipmentUnit::where('id', $unitId)
                    ->whereIn('condition', ['new', 'refurbished'])
                    ->lockForUpdate()
                    ->first();

                if (! $unit) {
                    throw new \Exception("Unit ID {$unitId} is no longer available.");
                }

                $issuance = \App\Models\EquipmentIssuance::create([
                    'customer_id'           => $customerId,
                    'product_id'            => $productId,
                    'equipment_unit_id'     => $unit->id,
                    'warehouse_id'          => $warehouseId,
                    'issued_by'             => auth()->id(),
                    'quantity'              => 1,
                    'serial_number'         => $unit->serial_number,
                    'issued_date'           => $issuedDate,
                    'expected_return_date'  => $expectedReturnDate,
                    'status'                => 'active',
                    'notes'                 => $notes,
                ]);

                $unit->update([
                    'condition'           => 'in_use',
                    'current_customer_id' => $customerId,
                ]);

                $issuances->push($issuance);
            }

            return $issuances;
        });
    }
    /**
     * Process equipment return from a customer.
     * If condition is good → restock automatically.
     */
    public function returnFromCustomer(
        int    $issuanceId,
        string $condition,
        string $action,
        string $returnDate,
        string $notes = null,
    ): \App\Models\EquipmentReturn {
        return DB::transaction(function () use (
            $issuanceId, $condition, $action, $returnDate, $notes
        ) {
            $issuance = \App\Models\EquipmentIssuance::findOrFail($issuanceId);

            $return = \App\Models\EquipmentReturn::create([
                'issuance_id'  => $issuance->id,
                'customer_id'  => $issuance->customer_id,
                'product_id'   => $issuance->product_id,
                'warehouse_id' => $issuance->warehouse_id,
                'received_by'  => auth()->id(),
                'quantity'     => $issuance->quantity,
                'return_date'  => $returnDate,
                'condition'    => $condition,
                'action'       => $action,
                'notes'        => $notes,
            ]);

            $issuance->update(['status' => 'returned']);

            // ── Update the individual unit condition ──────────────────────
            if ($issuance->equipment_unit_id) {
                $newCondition = match($action) {
                    'restock' => 'refurbished',
                    'repair'  => 'under_repair',
                    'scrap'   => 'scrapped',
                    default   => 'refurbished',
                };

                \App\Models\EquipmentUnit::find($issuance->equipment_unit_id)?->update([
                    'condition'           => $newCondition,
                    'current_customer_id' => null,
                ]);
            }

            // If action is restock → add back to stock
            // If action is restock → add back to stock
            if ($action === 'restock' && ! $issuance->product->is_serialized) {
                $this->adjust(
                    productId:   $issuance->product_id,
                    warehouseId: $issuance->warehouse_id,
                    quantity:    $issuance->quantity,
                    type:        'return',
                    reference:   "RTN-{$issuance->customer_id}",
                    notes:       "Returned by customer ID {$issuance->customer_id} — condition: {$condition}",
                );
            }

            return $return;
        });
    }
    // ── private ──────────────────────────────────────────────────────────────

    private function checkLowStock(int $productId, int $currentQty): void
    {
        $product = Product::find($productId);

        if ($product && $currentQty <= $product->reorder_point) {
            Log::warning(
                "LOW STOCK: [{$product->sku}] {$product->name} — " .
                "qty: {$currentQty}, reorder point: {$product->reorder_point}"
            );
        }
    }
}