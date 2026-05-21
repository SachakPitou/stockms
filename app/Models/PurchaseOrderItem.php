<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id', 'product_id',
        'qty_ordered', 'qty_received',
        'unit_price', 'customisation',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getLineTotalAttribute(): float
    {
        return $this->qty_ordered * $this->unit_price;
    }

    public function getRemainingAttribute(): int
    {
        return $this->qty_ordered - $this->qty_received;
    }
}