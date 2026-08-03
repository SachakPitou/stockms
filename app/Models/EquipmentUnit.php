<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class EquipmentUnit extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'product_id', 'warehouse_id', 'serial_number',
        'condition', 'current_customer_id',
        'added_by', 'purchase_date', 'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['condition', 'current_customer_id', 'warehouse_id'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) =>
                "Equipment unit [{$this->serial_number}] was {$eventName}"
            );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function currentCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'current_customer_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    // ── Condition helpers ─────────────────────────────────────────────

    public function isAvailable(): bool
    {
        return in_array($this->condition, ['new', 'refurbished']);
    }

    public function getConditionLabelAttribute(): string
    {
        return match($this->condition) {
            'new'          => '🟢 New',
            'in_use'       => '🔵 In Use',
            'refurbished'  => '🟡 Refurbished (Ready)',
            'under_repair' => '🔧 Under Repair',
            'scrapped'     => '❌ Scrapped',
            default        => ucfirst($this->condition),
        };
    }

    public function getConditionColorAttribute(): string
    {
        return match($this->condition) {
            'new'          => 'success',
            'in_use'       => 'info',
            'refurbished'  => 'warning',
            'under_repair' => 'gray',
            'scrapped'     => 'danger',
            default        => 'gray',
        };
    }
}