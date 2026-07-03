<?php

namespace Tests\Feature;

use App\Enums\EvaluationType;
use App\Enums\GameAnalysisStatus;
use App\Enums\GameSource;
use App\Enums\PieceColor;
use App\Models\Game;
use App\Models\Move;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_games_table_stores_game_metadata(): void
    {
        $user = User::factory()->create();

        $game = Game::factory()->for($user)->fromChessCom('https://chess.com/game/123')->create([
            'white_player' => 'Alice',
            'black_player' => 'Bob',
            'result' => '1-0',
        ]);

        $this->assertDatabaseHas('games', [
            'id' => $game->id,
            'user_id' => $user->id,
            'source' => GameSource::ChessCom->value,
            'source_game_id' => 'https://chess.com/game/123',
            'white_player' => 'Alice',
            'black_player' => 'Bob',
            'result' => '1-0',
            'analysis_status' => GameAnalysisStatus::Pending->value,
        ]);
    }

    public function test_moves_table_stores_move_and_evaluation_data(): void
    {
        $game = Game::factory()->create();

        $move = Move::factory()->for($game)->create([
            'ply' => 1,
            'move_number' => 1,
            'color' => PieceColor::White,
            'san' => 'e4',
            'uci' => 'e2e4',
            'fen_after' => 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1',
            'evaluation' => 25,
            'evaluation_type' => EvaluationType::Centipawn,
            'best_move' => 'e2e4',
            'classification' => 'excellent',
        ]);

        $this->assertDatabaseHas('moves', [
            'id' => $move->id,
            'game_id' => $game->id,
            'ply' => 1,
            'san' => 'e4',
            'evaluation' => 25,
            'evaluation_type' => EvaluationType::Centipawn->value,
        ]);
    }

    public function test_game_has_ordered_moves_relationship(): void
    {
        $game = Game::factory()->create();

        Move::factory()->for($game)->create(['ply' => 3, 'move_number' => 2, 'color' => PieceColor::Black, 'san' => 'e5']);
        Move::factory()->for($game)->create(['ply' => 1, 'move_number' => 1, 'color' => PieceColor::White, 'san' => 'e4']);
        Move::factory()->for($game)->create(['ply' => 2, 'move_number' => 1, 'color' => PieceColor::Black, 'san' => 'c5']);

        $this->assertSame(
            ['e4', 'c5', 'e5'],
            $game->moves()->pluck('san')->all(),
        );
    }

    public function test_deleting_game_cascades_to_moves(): void
    {
        $game = Game::factory()->has(Move::factory()->count(3))->create();

        $game->delete();

        $this->assertDatabaseMissing('games', ['id' => $game->id]);
        $this->assertDatabaseCount('moves', 0);
    }

    public function test_external_game_ids_are_unique_per_source(): void
    {
        Game::factory()->fromLichess('abc123')->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Game::factory()->fromLichess('abc123')->create();
    }
}
