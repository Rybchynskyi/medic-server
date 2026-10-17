<?php

namespace App\Models;

use App\Enums\Period\PeriodStatuses;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Period extends Model
{
    protected $fillable = [
        'identifier',
        'organization_id',
        'created_by',
        'month',
        'year',
        'status',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(PeriodEntry::class);
    }

    public function materialTotals(): HasMany
    {
        return $this->hasMany(PeriodMaterialTotal::class);
    }

    public function isOpen(): bool
    {
        return $this->status === PeriodStatuses::OPEN;
    }
}
