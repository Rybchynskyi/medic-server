<?php

namespace App\Repositories;

use App\Models\User;

class UsersRepository extends CoreRepository
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function create(array $data): User
    {
        $user = new User($data);
        $user->identifier = $this->generateIdentifier();
        $this->save($user);

        return $user;
    }

    public function allWithRelations()
    {
        return $this->model->with(['organization', 'roles', 'modules'])->orderBy('name')->get();
    }
}
