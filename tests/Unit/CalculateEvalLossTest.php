<?php

namespace Tests\Unit;

use App\Enums\EvaluationType;
use App\Enums\PieceColor;
use App\Services\Analysis\CalculateEvalLoss;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CalculateEvalLossTest extends TestCase
{
    private CalculateEvalLoss $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new CalculateEvalLoss;
    }

    #[DataProvider('centipawnLossProvider')]
    public function test_calculates_centipawn_loss_for_the_moving_player(
        PieceColor $color,
        int $evalBefore,
        int $evalAfter,
        int $expectedLoss,
    ): void {
        $loss = $this->calculator->calculate(
            $color,
            $evalBefore,
            EvaluationType::Centipawn,
            $evalAfter,
            EvaluationType::Centipawn,
        );

        $this->assertSame($expectedLoss, $loss);
    }

    /**
     * @return array<string, array{PieceColor, int, int, int}>
     */
    public static function centipawnLossProvider(): array
    {
        return [
            'white blunder loses centipawns' => [PieceColor::White, 50, 20, 30],
            'white good move can have negative loss' => [PieceColor::White, 0, 25, -25],
            'black good move can have negative loss' => [PieceColor::Black, 25, 15, -10],
            'black blunder loses centipawns' => [PieceColor::Black, 10, 40, 30],
        ];
    }

    public function test_returns_null_when_before_eval_is_mate(): void
    {
        $loss = $this->calculator->calculate(
            PieceColor::White,
            2,
            EvaluationType::Mate,
            25,
            EvaluationType::Centipawn,
        );

        $this->assertNull($loss);
    }

    public function test_returns_null_when_after_eval_is_mate(): void
    {
        $loss = $this->calculator->calculate(
            PieceColor::White,
            25,
            EvaluationType::Centipawn,
            0,
            EvaluationType::Mate,
        );

        $this->assertNull($loss);
    }
}
