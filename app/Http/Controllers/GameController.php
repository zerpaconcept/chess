<?php

namespace App\Http\Controllers;

use App\Enums\GameAnalysisStatus;
use App\Jobs\AnalyzeGameJob;
use App\Models\Game;
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

        $game->update(['analysis_status' => GameAnalysisStatus::InProgress]);

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
