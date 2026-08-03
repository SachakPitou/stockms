<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;   // 👈 add this
use Spatie\Activitylog\LogOptions;  

class Product extends Model
{
    use SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'sku', 'unit_cost', 'selling_price', 'reorder_point', 'is_active'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Product was {$eventName}");
    }

    protected $fillable = [
        'sku', 'name', 'description', 'category_id', 'supplier_id',
        'unit', 'unit_cost', 'cost_currency', 'selling_price',
        'reorder_point', 'reorder_qty', 'barcode', 'image', 'is_active',
        'is_serialized', // 👈 add
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'is_serialized'  => 'boolean', // 👈 add
        'unit_cost'      => 'decimal:2',
        'selling_price'  => 'decimal:2',
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
    public function equipmentUnits(): HasMany
    {
        return $this->hasMany(EquipmentUnit::class);
    }

    public function availableUnits(): HasMany
    {
        return $this->hasMany(EquipmentUnit::class)
            ->whereIn('condition', ['new', 'refurbished']);
    }
}