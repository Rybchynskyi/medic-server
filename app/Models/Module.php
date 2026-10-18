<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Module extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'enabled_for_new_organizations',
    ];

    protected function casts(): array
    {
        return [
            'enabled_for_new_organizations' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_modules')
            ->withPivot('enabled')
            ->withTimestamps();
    }
}
