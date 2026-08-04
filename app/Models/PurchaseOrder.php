<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;   // 👈 add this
use Spatie\Activitylog\LogOptions;  

class PurchaseOrder extends Model
{
    use SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'total', 'tracking_number', 'received_date'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Purchase order was {$eventName}");
    }

    protected $fillable = [
        'po_number', 'supplier_id', 'warehouse_id', 'user_id',
        'status', 'order_date', 'expected_date', 'received_date',
        'currency', 'exchange_rate', 'freight_cost', 'customs_duty',
        'total', 'tracking_number', 'notes',
    ];

    protected $casts = [
        'order_date'    => 'date',
        'expected_date' => 'date',
        'received_date' => 'date',
        'exchange_rate' => 'decimal:6',
        'freight_cost'  => 'decimal:2',
        'customs_duty'  => 'decimal:2',
        'total'         => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function getLandedCostAttribute(): float
    {
        return ($this->total + $this->freight_cost + $this->customs_duty)
            * $this->exchange_rate;
    }
    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

}