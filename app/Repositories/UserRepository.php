<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    protected $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function findUserWithTrash($user_id)
    {
       return $this->user::withTrashed()->where("id", '=', $user_id)->first();
    }
}
