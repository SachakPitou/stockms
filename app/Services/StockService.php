<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            $level = StockLevel::firstOrCreate(
                ['product_id' => $productId, 'warehouse_id' => $warehouseId],
                ['quantity' => 0, 'reserved' => 0],
            );

            $qtyBefore = $level->quantity;
            $isOutbound = in_array($type, ['issue', 'transfer']);
            $change     = $isOutbound ? -abs($quantity) : abs($quantity);
            $qtyAfter   = max(0, $qtyBefore + $change);

            $level->update(['quantity' => $qtyAfter]);

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
    public function receivePurchaseOrder(PurchaseOrder $po, array $receivedQtys): void
    {
        DB::transaction(function () use ($po, $receivedQtys) {
            foreach ($receivedQtys as $itemId => $qty) {
                if ($qty <= 0) continue;

                $item       = $po->items()->findOrFail($itemId);
                $allowedQty = $item->qty_ordered - $item->qty_received;
                $qty        = min((int) $qty, $allowedQty);

                if ($qty <= 0) continue;

                $item->increment('qty_received', $qty);

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

            $status = match(true) {
                $totalReceived <= 0             => $po->status,
                $totalReceived >= $totalOrdered => 'received',
                default                         => 'partially_received',
            };

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