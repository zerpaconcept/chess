<?php

namespace App\Exceptions\ChessCom;

use Exception;

class PlayerNotFoundException extends Exception
{
    public function __construct(public readonly string $username)
    {
        parent::__construct("Chess.com player [{$username}] was not found.");
    }
}
