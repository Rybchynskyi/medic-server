<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'identifier',
        'name',
        'edrpou',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(Period::class);
    }
}
