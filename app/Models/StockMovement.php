<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;  // 👈 add this
use Spatie\Activitylog\LogOptions;    // 👈 add this

class StockMovement extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->setDescriptionForEvent(fn(string $eventName) => "Stock movement was {$eventName}");
    }

    protected $fillable = [
        'product_id', 'warehouse_id', 'user_id', 'type',
        'quantity', 'quantity_before', 'quantity_after',
        'reference', 'notes', 'moved_at',
    ];

    protected $casts = [
        'moved_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}