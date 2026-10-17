<?php

namespace App\Repositories;

use App\Models\PeriodMaterialTotal;
use Illuminate\Support\Collection;

class PeriodMaterialTotalRepository extends CoreRepository
{
    public function __construct(PeriodMaterialTotal $model)
    {
        parent::__construct($model);
    }

    public function createMany(array $rows): void
    {
        $this->model->insert($rows);
    }

    public function allForPeriod(int $periodId): Collection
    {
        return $this->model->with('material')->where('period_id', $periodId)->get();
    }
}
