<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceMaterialNorm extends Model
{
    protected $fillable = [
        'organization_id',
        'service_id',
        'material_id',
        'quantity_per_service',
    ];

    protected function casts(): array
    {
        return [
            'quantity_per_service' => 'decimal:4',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
