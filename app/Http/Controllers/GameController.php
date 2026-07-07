<?php

namespace App\Http\Controllers;

use App\Enums\GameAnalysisStatus;
use App\Jobs\AnalyzeGameJob;
use App\Models\Game;
use App\Models\Move;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    /**
     * Display the user's games.
     */
    public function index(Request $request): Response
    {
        $games = $request->user()
            ->games()
            ->withCount('moves')
            ->latest()
            ->paginate(15)
            ->through(fn (Game $game) => [
                'id' => $game->id,
                'white_player' => $game->white_player,
                'black_player' => $game->black_player,
                'white_elo' => $game->white_elo,
                'black_elo' => $game->black_elo,
                'result' => $game->result,
                'source' => $game->source->value,
                'played_at' => $game->played_at?->toDateString(),
                'opening' => $game->opening,
                'analysis_status' => $game->analysis_status->value,
                'analysis_depth' => $game->analysis_depth,
                'analyzed_at' => $game->analyzed_at?->toIso8601String(),
                'moves_count' => $game->moves_count,
                'can_analyze' => $game->analysis_status !== GameAnalysisStatus::InProgress && filled($game->pgn),
            ]);

        return Inertia::render('games/index', [
            'games' => $games,
        ]);
    }

    /**
     * Review a game with move list and evaluation chart.
     */
    public function show(Request $request, Game $game): Response
    {
        $this->authorize('view', $game);

        $game->load('moves');

        return Inertia::render('games/show', [
            'game' => [
                'id' => $game->id,
                'white_player' => $game->white_player,
                'black_player' => $game->black_player,
                'white_elo' => $game->white_elo,
                'black_elo' => $game->black_elo,
                'result' => $game->result,
                'source' => $game->source->value,
                'played_at' => $game->played_at?->toDateString(),
                'opening' => $game->opening,
                'eco' => $game->eco,
                'event' => $game->event,
                'time_control' => $game->time_control,
                'analysis_status' => $game->analysis_status->value,
                'analysis_depth' => $game->analysis_depth,
                'analyzed_at' => $game->analyzed_at?->toIso8601String(),
                'initial_fen' => $game->initial_fen ?? 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1',
                'can_analyze' => $game->analysis_status !== GameAnalysisStatus::InProgress && filled($game->pgn),
            ],
            'moves' => $game->moves->map(fn (Move $move) => [
                'id' => $move->id,
                'ply' => $move->ply,
                'move_number' => $move->move_number,
                'color' => $move->color->value,
                'san' => $move->san,
                'uci' => $move->uci,
                'fen_after' => $move->fen_after,
                'evaluation' => $move->evaluation,
                'evaluation_type' => $move->evaluation_type?->value,
                'eval_loss' => $move->eval_loss,
                'best_move' => $move->best_move,
                'classification' => $move->classification,
            ]),
        ]);
    }

    /**
     * Queue Stockfish analysis for a game.
     */
    public function analyze(Request $request, Game $game): RedirectResponse
    {
        $this->authorize('analyze', $game);

        if ($game->analysis_status === GameAnalysisStatus::InProgress) {
            return back()->withErrors([
                'analysis' => __('This game is already being analyzed.'),
            ]);
        }

        if (blank($game->pgn)) {
            return back()->withErrors([
                'analysis' => __('This game has no PGN to analyze.'),
            ]);
        }

        AnalyzeGameJob::dispatch($game);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Analysis queued for :white vs :black.', [
                'white' => $game->white_player,
                'black' => $game->black_player,
            ]),
        ]);

        return back();
    }
}
