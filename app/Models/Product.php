<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sku', 'name', 'description', 'category_id', 'supplier_id',
        'unit', 'unit_cost', 'cost_currency', 'selling_price',
        'reorder_point', 'reorder_qty', 'barcode', 'image', 'is_active',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'unit_cost'     => 'decimal:2',
        'selling_price' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function getTotalStockAttribute(): int
    {
        return $this->stockLevels->sum('quantity');
    }

    public function isLowStock(): bool
    {
        return $this->total_stock <= $this->reorder_point;
    }
}