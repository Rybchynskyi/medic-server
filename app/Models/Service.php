<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'identifier',
        'organization_id',
        'code',
        'name',
        'unit',
        'category',
        'contract',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function materialNorms(): HasMany
    {
        return $this->hasMany(ServiceMaterialNorm::class);
    }

    public function periodEntries(): HasMany
    {
        return $this->hasMany(PeriodEntry::class);
    }
}
