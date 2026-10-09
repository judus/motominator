<?php

namespace App\Garage\Policies;

use App\Models\Motorcycle;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MotorcyclePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }

    public function view(User $user, Motorcycle $record): Response
    {
        return $user->is_admin || $user->id === $record->user_id
            ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, Motorcycle $record): Response
    {
        return $this->view($user, $record)->allowed() && $user->hasVerifiedEmail()
            ? Response::allow() : Response::denyAsNotFound();
    }

    public function delete(User $user, Motorcycle $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Motorcycle $record): bool
    {
        return false;
    }

    public function forceDelete(User $user, Motorcycle $record): bool
    {
        return false;
    }
}
