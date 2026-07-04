<?php

namespace App\Services\Analysis;

use App\Enums\GameAnalysisStatus;
use App\Models\Game;
use App\Services\Pgn\StoreGameMoves;
use App\Services\Stockfish\StockfishEngine;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class AnalyzeGame
{
    private const string STARTING_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

    public function __construct(
        private readonly StoreGameMoves $storeGameMoves,
        private readonly StockfishEngine $stockfishEngine,
        private readonly CalculateEvalLoss $calculateEvalLoss,
    ) {}

    public function analyze(Game $game): void
    {
        if (blank($game->pgn)) {
            throw new RuntimeException('Game has no PGN to analyze.');
        }

        $game->update(['analysis_status' => GameAnalysisStatus::InProgress]);

        $depth = (int) config('services.stockfish.depth', 18);

        try {
            DB::transaction(function () use ($game, $depth): void {
                $this->storeGameMoves->store($game);

                $game->load('moves');

                $previousAnalysis = $this->stockfishEngine->analyze(
                    $game->initial_fen ?? self::STARTING_FEN,
                    $depth,
                );

                foreach ($game->moves as $move) {
                    $analysis = $this->stockfishEngine->analyze($move->fen_after, $depth);

                    $evalLoss = $this->calculateEvalLoss->calculate(
                        $move->color,
                        $previousAnalysis->evaluation,
                        $previousAnalysis->evaluationType,
                        $analysis->evaluation,
                        $analysis->evaluationType,
                    );

                    $move->update([
                        'evaluation' => $analysis->evaluation,
                        'evaluation_type' => $analysis->evaluationType,
                        'best_move' => $analysis->bestMove,
                        'eval_loss' => $evalLoss,
                    ]);

                    $previousAnalysis = $analysis;
                }

                $game->update([
                    'analysis_status' => GameAnalysisStatus::Completed,
                    'analysis_depth' => $depth,
                    'analyzed_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            $game->update(['analysis_status' => GameAnalysisStatus::Failed]);

            throw $exception;
        }
    }
}
