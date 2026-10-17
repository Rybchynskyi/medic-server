<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodMaterialTotal extends Model
{
    protected $fillable = [
        'period_id',
        'material_id',
        'total_quantity',
    ];

    protected function casts(): array
    {
        return [
            'total_quantity' => 'decimal:4',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
