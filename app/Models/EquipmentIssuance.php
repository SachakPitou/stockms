<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class EquipmentIssuance extends Model
{
    use LogsActivity;

    protected $fillable = [
        'customer_id', 'product_id', 'equipment_unit_id', 'warehouse_id', 'issued_by',
        'quantity', 'serial_number', 'issued_date',
        'expected_return_date', 'status', 'notes',
    ];

    protected $casts = [
        'issued_date'          => 'date',
        'expected_return_date' => 'date',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'serial_number'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Equipment issuance was {$eventName}");
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

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function return(): HasOne
    {
        return $this->hasOne(EquipmentReturn::class, 'issuance_id');
    }
    public function unit(): BelongsTo
    {
        return $this->belongsTo(EquipmentUnit::class, 'equipment_unit_id');
    }
}