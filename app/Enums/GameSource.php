<?php

namespace App\Enums;

enum GameSource: string
{
    case Manual = 'manual';
    case Pgn = 'pgn';
    case ChessCom = 'chess_com';
    case Lichess = 'lichess';
}
