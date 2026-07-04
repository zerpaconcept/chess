<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameListTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_games_list(): void
    {
        $this->get(route('games.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_their_games(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create([
            'white_player' => 'Alice',
            'black_player' => 'Bob',
            'pgn' => "[Event \"Test\"]\n\n1. e4 e5 1-0\n",
        ]);

        $this->actingAs($user)
            ->get(route('games.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('games/index')
                ->has('games.data', 1)
                ->where('games.data.0.id', $game->id)
                ->where('games.data.0.white_player', 'Alice')
                ->where('games.data.0.can_analyze', true));
    }

    public function test_users_only_see_their_own_games(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Game::factory()->for($user)->create();
        Game::factory()->for($otherUser)->create();

        $this->actingAs($user)
            ->get(route('games.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('games.data', 1));
    }
}
