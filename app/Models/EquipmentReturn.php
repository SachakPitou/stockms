<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class EquipmentReturn extends Model
{
    use LogsActivity;

    protected $fillable = [
        'issuance_id', 'customer_id', 'product_id', 'warehouse_id',
        'received_by', 'quantity', 'return_date',
        'condition', 'action', 'notes',
    ];

    protected $casts = [
        'return_date' => 'date',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['condition', 'action'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Equipment return was {$eventName}");
    }

    public function issuance(): BelongsTo
    {
        return $this->belongsTo(EquipmentIssuance::class, 'issuance_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}