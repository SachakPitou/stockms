<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class PurchaseRequest extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'pr_number', 'supplier_id', 'warehouse_id',
        'requested_by', 'verified_by', 'approved_by',
        'status', 'reason', 'rejection_reason',
        'verification_comment', 'notes', 'attachment_path',
        'submitted_at', 'verified_at', 'approved_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'verified_at'  => 'datetime',
        'approved_at'  => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) =>
                "Purchase request was {$eventName}"
            );
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }
}