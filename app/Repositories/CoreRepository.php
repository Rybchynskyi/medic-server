<?php

namespace App\Repositories;

use App\Traits\HasIdentifier;
use Illuminate\Database\Eloquent\Model;

abstract class CoreRepository
{
    use HasIdentifier;

    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * @param  string|array|int  $id
     */
    public function find($id, array $with = [])
    {
        return $this->model->with($with)->find($id);
    }

    /**
     * @param  string|array|int  $id
     */
    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }

    public function findOneBy(array $criteria)
    {
        return $this->model->where($criteria)->first();
    }

    public function findBy(array $criteria)
    {
        return $this->model->where($criteria)->get();
    }

    public function findByIdentifier(string $identifier, array $with = [])
    {
        return $this->model->with($with)->where('identifier', $identifier)->first();
    }

    public function findByIdentifierOrFail(string $identifier, array $with = [])
    {
        return $this->model->with($with)->where('identifier', $identifier)->firstOrFail();
    }

    public function all()
    {
        return $this->model->all();
    }

    public function save($model): void
    {
        if (! $model->save()) {
            throw new \RuntimeException('Saving error.');
        }
    }

    public function delete($model): void
    {
        if (! $model->delete()) {
            throw new \RuntimeException('Deleting error.');
        }
    }

    public function findByWhereIn(string $column, array $values)
    {
        return $this->model->whereIn($column, $values)->get();
    }
}
