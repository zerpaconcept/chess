<?php

namespace Tests\Feature;

use App\Enums\GameSource;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChessComImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_chess_com_import_page(): void
    {
        $this->get(route('import.chess-com.create'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_chess_com_import_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('import.chess-com.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('import/chess-com')
                ->has('timeClassOptions', 4));
    }

    public function test_import_requires_valid_form_data(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('import.chess-com.store'), [])
            ->assertSessionHasErrors(['username', 'from_date', 'to_date', 'time_classes', 'color']);
    }

    public function test_import_returns_error_when_player_not_found(): void
    {
        Http::fake([
            'api.chess.com/pub/player/unknown-player' => Http::response([], 404),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('import.chess-com.store'), [
                'username' => 'unknown-player',
                'from_date' => '2024-01-01',
                'to_date' => '2024-01-31',
                'time_classes' => ['blitz'],
                'color' => 'both',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('username');
    }

    public function test_import_stores_matching_games_from_chess_com(): void
    {
        $username = 'testplayer';

        Http::fake([
            "api.chess.com/pub/player/{$username}" => Http::response([
                '@id' => "https://api.chess.com/pub/player/{$username}",
                'username' => $username,
            ]),
            "api.chess.com/pub/player/{$username}/games/archives" => Http::response([
                'archives' => [
                    "https://api.chess.com/pub/player/{$username}/games/2024/01",
                ],
            ]),
            "api.chess.com/pub/player/{$username}/games/2024/01" => Http::response([
                'games' => [
                    [
                        'url' => 'https://www.chess.com/game/live/111',
                        'pgn' => "[Event \"Live Chess\"]\n[Result \"1-0\"]\n\n1. e4 e5 1-0",
                        'time_control' => '180',
                        'end_time' => 1705276800,
                        'time_class' => 'blitz',
                        'white' => ['username' => 'TestPlayer', 'rating' => 1500, 'result' => 'win'],
                        'black' => ['username' => 'Opponent', 'rating' => 1480, 'result' => 'checkmated'],
                    ],
                    [
                        'url' => 'https://www.chess.com/game/live/222',
                        'pgn' => "[Event \"Live Chess\"]\n[Result \"0-1\"]\n\n1. d4 d5 0-1",
                        'time_control' => '600',
                        'end_time' => 1705276800,
                        'time_class' => 'rapid',
                        'white' => ['username' => 'TestPlayer', 'rating' => 1500, 'result' => 'resigned'],
                        'black' => ['username' => 'Opponent2', 'rating' => 1520, 'result' => 'win'],
                    ],
                    [
                        'url' => 'https://www.chess.com/game/live/333',
                        'pgn' => "[Event \"Live Chess\"]\n[Result \"1-0\"]\n\n1. c4 e5 1-0",
                        'time_control' => '180',
                        'end_time' => 1705276800,
                        'time_class' => 'blitz',
                        'white' => ['username' => 'SomeoneElse', 'rating' => 1600, 'result' => 'win'],
                        'black' => ['username' => 'TestPlayer', 'rating' => 1500, 'result' => 'checkmated'],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('import.chess-com.store'), [
                'username' => $username,
                'from_date' => '2024-01-15',
                'to_date' => '2024-01-15',
                'time_classes' => ['blitz'],
                'color' => 'white',
            ])
            ->assertRedirect(route('import.chess-com.create'));

        $this->assertDatabaseHas('games', [
            'user_id' => $user->id,
            'source' => GameSource::ChessCom->value,
            'source_game_id' => 'https://www.chess.com/game/live/111',
            'white_player' => 'TestPlayer',
            'result' => '1-0',
        ]);

        $this->assertDatabaseMissing('games', [
            'source_game_id' => 'https://www.chess.com/game/live/222',
        ]);

        $this->assertDatabaseMissing('games', [
            'source_game_id' => 'https://www.chess.com/game/live/333',
        ]);

        $game = Game::query()->where('source_game_id', 'https://www.chess.com/game/live/111')->first();

        $this->assertNotNull($game);
        $this->assertNotNull($game->pgn);
    }

    public function test_import_skips_existing_games(): void
    {
        $username = 'testplayer';
        $gameUrl = 'https://www.chess.com/game/live/111';

        Game::factory()->fromChessCom($gameUrl)->create([
            'source_game_id' => $gameUrl,
        ]);

        Http::fake([
            "api.chess.com/pub/player/{$username}" => Http::response(['username' => $username]),
            "api.chess.com/pub/player/{$username}/games/archives" => Http::response([
                'archives' => ["https://api.chess.com/pub/player/{$username}/games/2024/01"],
            ]),
            "api.chess.com/pub/player/{$username}/games/2024/01" => Http::response([
                'games' => [
                    [
                        'url' => $gameUrl,
                        'pgn' => "[Result \"1-0\"]\n\n1. e4 e5 1-0",
                        'time_control' => '180',
                        'end_time' => 1705276800,
                        'time_class' => 'blitz',
                        'white' => ['username' => 'TestPlayer', 'rating' => 1500, 'result' => 'win'],
                        'black' => ['username' => 'Opponent', 'rating' => 1480, 'result' => 'checkmated'],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('import.chess-com.store'), [
                'username' => $username,
                'from_date' => '2024-01-15',
                'to_date' => '2024-01-15',
                'time_classes' => ['blitz'],
                'color' => 'both',
            ])
            ->assertRedirect(route('import.chess-com.create'));

        $this->assertDatabaseCount('games', 1);
    }
}
