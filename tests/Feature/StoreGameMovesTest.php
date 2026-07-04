<?php

namespace Tests\Feature;

use App\Enums\PieceColor;
use App\Models\Game;
use App\Models\Move;
use App\Services\Pgn\StoreGameMoves;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreGameMovesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_parses_pgn_and_stores_moves(): void
    {
        $game = Game::factory()->create([
            'pgn' => <<<'PGN'
[Event "Test Game"]

1. e4 e5 2. Nf3 Nc6 3. Bb5 a6 1-0
PGN,
        ]);

        $stored = app(StoreGameMoves::class)->store($game);

        $this->assertSame(6, $stored);
        $this->assertDatabaseCount('moves', 6);

        $firstMove = Move::query()->where('game_id', $game->id)->where('ply', 1)->first();

        $this->assertNotNull($firstMove);
        $this->assertSame('e4', $firstMove->san);
        $this->assertSame('e2e4', $firstMove->uci);
        $this->assertSame(PieceColor::White, $firstMove->color);
        $this->assertSame(
            'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1',
            $firstMove->fen_after,
        );

        $lastMove = Move::query()->where('game_id', $game->id)->where('ply', 6)->first();

        $this->assertNotNull($lastMove);
        $this->assertSame('a6', $lastMove->san);
        $this->assertSame(PieceColor::Black, $lastMove->color);
    }

    public function test_it_replaces_existing_moves_when_called_again(): void
    {
        $game = Game::factory()->create([
            'pgn' => "1. d4 d5 1-0\n",
        ]);

        $service = app(StoreGameMoves::class);

        $service->store($game);
        $service->store($game);

        $this->assertDatabaseCount('moves', 2);
    }

    public function test_it_returns_zero_when_game_has_no_pgn(): void
    {
        $game = Game::factory()->create(['pgn' => null]);

        $stored = app(StoreGameMoves::class)->store($game);

        $this->assertSame(0, $stored);
        $this->assertDatabaseCount('moves', 0);
    }

    public function test_it_resolves_uci_for_castling_moves(): void
    {
        $game = Game::factory()->create([
            'pgn' => <<<'PGN'
[Event "Castling test"]

1. e4 e5 2. Nf3 Nc6 3. Bc4 Nf6 4. O-O Be7 5. Re1 O-O 1/2-1/2
PGN,
        ]);

        app(StoreGameMoves::class)->store($game);

        $this->assertDatabaseHas('moves', [
            'game_id' => $game->id,
            'san' => 'O-O',
            'uci' => 'e1g1',
            'ply' => 7,
        ]);

        $this->assertDatabaseHas('moves', [
            'game_id' => $game->id,
            'san' => 'O-O',
            'uci' => 'e8g8',
            'ply' => 10,
        ]);
    }

    public function test_it_resolves_ambiguous_moves_using_legal_move_matching(): void
    {
        $game = Game::factory()->create([
            'pgn' => <<<'PGN'
[SetUp "1"]
[FEN "8/8/8/8/2R1R3/8/8/k6K w - - 0 1"]

1. Red4+ 1-0
PGN,
        ]);

        app(StoreGameMoves::class)->store($game);

        $this->assertDatabaseHas('moves', [
            'game_id' => $game->id,
            'san' => 'Red4+',
            'uci' => 'e4d4',
            'ply' => 1,
        ]);
    }

    public function test_it_resolves_moves_when_only_one_candidate_is_legal(): void
    {
        $game = Game::factory()->create([
            'pgn' => <<<'PGN'
[SetUp "1"]
[FEN "7k/8/8/8/4R3/8/4R3/K7 w - - 0 1"]

1. Re8+ 1-0
PGN,
        ]);

        app(StoreGameMoves::class)->store($game);

        $this->assertDatabaseHas('moves', [
            'game_id' => $game->id,
            'san' => 'Re8+',
            'uci' => 'e4e8',
            'ply' => 1,
        ]);
    }

    public function test_it_parses_games_with_chess_com_clock_comments(): void
    {
        $game = Game::factory()->create([
            'pgn' => <<<'PGN'
[Event "Live Chess"]

1. e4 {[%clk 0:10:00]} e5 {[%clk 0:09:59]} 2. Nf3 Nc6 1-0
PGN,
        ]);

        app(StoreGameMoves::class)->store($game);

        $this->assertSame(4, Move::query()->where('game_id', $game->id)->count());
    }
}
