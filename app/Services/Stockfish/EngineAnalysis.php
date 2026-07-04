<?php

namespace App\Services\Stockfish;

use App\Enums\EvaluationType;

readonly class EngineAnalysis
{
    public function __construct(
        public int $evaluation,
        public EvaluationType $evaluationType,
        public ?string $bestMove,
        public int $depth,
    ) {}
}
