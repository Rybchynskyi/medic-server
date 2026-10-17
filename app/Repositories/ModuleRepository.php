<?php

namespace App\Repositories;

use App\Models\Module;

class ModuleRepository extends CoreRepository
{
    public function __construct(Module $model)
    {
        parent::__construct($model);
    }

    public function findByKey(string $key): ?Module
    {
        return $this->model->where('key', $key)->first();
    }
}
