<?php

namespace Tests\Unit;

use App\Enums\EvaluationType;
use App\Services\Stockfish\ParseStockfishOutput;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ParseStockfishOutputTest extends TestCase
{
    private ParseStockfishOutput $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new ParseStockfishOutput;
    }

    public function test_parses_deepest_score_line_with_pv(): void
    {
        $output = <<<'UCI'
info depth 17 seldepth 22 score cp 47 pv e2e4 c7c5
info depth 18 seldepth 29 score cp 49 pv e2e4 c7c6
bestmove e2e4 ponder c7c6
UCI;

        $analysis = $this->parser->parse(
            $output,
            'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1',
            18,
        );

        $this->assertSame(49, $analysis->evaluation);
        $this->assertSame(EvaluationType::Centipawn, $analysis->evaluationType);
        $this->assertSame('e2e4', $analysis->bestMove);
        $this->assertSame(18, $analysis->depth);
    }

    public function test_parses_terminal_mate_without_pv(): void
    {
        $output = <<<'UCI'
info string Available processors: 0-11
info depth 0 score mate 0
bestmove (none)
UCI;

        $analysis = $this->parser->parse(
            $output,
            '2r1r2k/pb2nN2/1pq1pNpP/4P3/1B1p4/8/PPP1QP2/2KR3R b - - 0 21',
            18,
        );

        $this->assertSame(0, $analysis->evaluation);
        $this->assertSame(EvaluationType::Mate, $analysis->evaluationType);
        $this->assertNull($analysis->bestMove);
        $this->assertSame(0, $analysis->depth);
    }

    #[DataProvider('scoreNormalizationProvider')]
    public function test_normalizes_scores_to_white_perspective(
        string $fen,
        string $output,
        int $expectedEvaluation,
        EvaluationType $expectedType,
    ): void {
        $analysis = $this->parser->parse($output, $fen, 18);

        $this->assertSame($expectedEvaluation, $analysis->evaluation);
        $this->assertSame($expectedType, $analysis->evaluationType);
    }

    /**
     * @return array<string, array{string, string, int, EvaluationType}>
     */
    public static function scoreNormalizationProvider(): array
    {
        return [
            'white to move keeps centipawn score' => [
                'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1',
                "info depth 10 score cp 25 pv e2e4\nbestmove e2e4\n",
                25,
                EvaluationType::Centipawn,
            ],
            'black to move flips centipawn score' => [
                'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1',
                "info depth 10 score cp 25 pv e7e5\nbestmove e7e5\n",
                -25,
                EvaluationType::Centipawn,
            ],
        ];
    }

    public function test_uses_bestmove_line_when_score_line_has_no_pv(): void
    {
        $output = <<<'UCI'
info depth 12 score cp 15
bestmove g1f3
UCI;

        $analysis = $this->parser->parse(
            $output,
            'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1',
            18,
        );

        $this->assertSame(15, $analysis->evaluation);
        $this->assertSame('g1f3', $analysis->bestMove);
    }
}
