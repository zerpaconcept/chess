<?php

namespace App\Services\Analysis;

use App\Enums\EvaluationType;
use App\Enums\PieceColor;

class CalculateEvalLoss
{
    /**
     * Centipawn loss for the player who made the move. Positive means the move worsened their position.
     */
    public function calculate(
        PieceColor $color,
        int $evalBefore,
        EvaluationType $evalBeforeType,
        int $evalAfter,
        EvaluationType $evalAfterType,
    ): ?int {
        if ($evalBeforeType !== EvaluationType::Centipawn || $evalAfterType !== EvaluationType::Centipawn) {
            return null;
        }

        return match ($color) {
            PieceColor::White => $evalBefore - $evalAfter,
            PieceColor::Black => $evalAfter - $evalBefore,
        };
    }
}
