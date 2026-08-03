<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestItem extends Model
{
    protected $fillable = [
        'purchase_request_id', 'product_id',
        'quantity', 'estimated_unit_price',
        'customisation', 'notes',
    ];

    protected $casts = [
        'estimated_unit_price' => 'decimal:2',
    ];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getEstimatedTotalAttribute(): float
    {
        return $this->quantity * $this->estimated_unit_price;
    }
}