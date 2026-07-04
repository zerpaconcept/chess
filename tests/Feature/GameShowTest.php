<?php

namespace Tests\Feature;

use App\Enums\EvaluationType;
use App\Enums\GameAnalysisStatus;
use App\Enums\PieceColor;
use App\Models\Game;
use App\Models\Move;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_a_game(): void
    {
        $game = Game::factory()->create();

        $this->get(route('games.show', $game))
            ->assertRedirect(route('login'));
    }

    public function test_users_cannot_view_another_users_game(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $game = Game::factory()->for($owner)->create();

        $this->actingAs($otherUser)
            ->get(route('games.show', $game))
            ->assertForbidden();
    }

    public function test_authenticated_users_can_review_their_game(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->analyzed()->create([
            'white_player' => 'Alice',
            'black_player' => 'Bob',
            'opening' => 'Italian Game',
            'initial_fen' => 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1',
        ]);

        Move::factory()->for($game)->create([
            'ply' => 1,
            'move_number' => 1,
            'color' => PieceColor::White,
            'san' => 'e4',
            'uci' => 'e2e4',
            'fen_after' => 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1',
            'evaluation' => 25,
            'evaluation_type' => EvaluationType::Centipawn,
            'eval_loss' => -25,
            'best_move' => 'e2e4',
        ]);

        Move::factory()->for($game)->create([
            'ply' => 2,
            'move_number' => 1,
            'color' => PieceColor::Black,
            'san' => 'e5',
            'uci' => 'e7e5',
            'fen_after' => 'rnbqkbnr/pppp1ppp/8/4p3/4P3/8/PPPP1PPP/RNBQKBNR w KQkq e6 0 2',
            'evaluation' => 15,
            'evaluation_type' => EvaluationType::Centipawn,
            'eval_loss' => -10,
            'best_move' => 'g1f3',
        ]);

        $this->actingAs($user)
            ->get(route('games.show', $game))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('games/show')
                ->where('game.id', $game->id)
                ->where('game.white_player', 'Alice')
                ->where('game.black_player', 'Bob')
                ->where('game.analysis_status', GameAnalysisStatus::Completed->value)
                ->where('game.initial_fen', 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1')
                ->has('moves', 2)
                ->where('moves.0.san', 'e4')
                ->where('moves.0.fen_after', 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1')
                ->where('moves.0.evaluation', 25)
                ->where('moves.0.eval_loss', -25)
                ->where('moves.1.san', 'e5')
                ->where('moves.1.eval_loss', -10));
    }
}
