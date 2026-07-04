<?php

namespace App\Enums;

enum ChessComTimeClass: string
{
    case Bullet = 'bullet';
    case Blitz = 'blitz';
    case Rapid = 'rapid';
    case Daily = 'daily';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
