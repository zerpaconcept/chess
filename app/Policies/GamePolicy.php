<?php

namespace App\Policies;

use App\Models\Game;
use App\Models\User;

class GamePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Game $game): bool
    {
        return $user->id === $game->user_id;
    }

    public function analyze(User $user, Game $game): bool
    {
        return $user->id === $game->user_id;
    }
}
