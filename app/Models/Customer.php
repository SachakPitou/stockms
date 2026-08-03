<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Customer extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'cid', 'name', 'phone', 'address', 'area', 'status', 'notes',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'cid', 'status'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Customer was {$eventName}");
    }

    public function issuances(): HasMany
    {
        return $this->hasMany(EquipmentIssuance::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(EquipmentReturn::class);
    }

    public function activeIssuances(): HasMany
    {
        return $this->hasMany(EquipmentIssuance::class)->where('status', 'active');
    }
}