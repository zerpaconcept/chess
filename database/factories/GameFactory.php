<?php

namespace Database\Factories;

use App\Enums\GameAnalysisStatus;
use App\Enums\GameSource;
use App\Models\Game;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'source' => GameSource::Manual,
            'source_game_id' => null,
            'white_player' => fake()->name(),
            'black_player' => fake()->name(),
            'white_elo' => fake()->numberBetween(1200, 2800),
            'black_elo' => fake()->numberBetween(1200, 2800),
            'result' => fake()->randomElement(['1-0', '0-1', '1/2-1/2', '*']),
            'event' => fake()->optional()->words(3, true),
            'site' => fake()->optional()->domainName(),
            'played_at' => fake()->optional()->date(),
            'time_control' => fake()->optional()->randomElement(['60+0', '180+2', '600+0']),
            'eco' => fake()->optional()->regexify('[A-E][0-9]{2}'),
            'opening' => fake()->optional()->words(2, true),
            'pgn' => null,
            'initial_fen' => null,
            'analysis_status' => GameAnalysisStatus::Pending,
            'analysis_depth' => null,
            'analyzed_at' => null,
        ];
    }

    public function fromChessCom(string $sourceGameId): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => GameSource::ChessCom,
            'source_game_id' => $sourceGameId,
        ]);
    }

    public function fromLichess(string $sourceGameId): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => GameSource::Lichess,
            'source_game_id' => $sourceGameId,
        ]);
    }

    public function fromPgn(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => GameSource::Pgn,
        ]);
    }

    public function analyzed(int $depth = 18): static
    {
        return $this->state(fn (array $attributes) => [
            'analysis_status' => GameAnalysisStatus::Completed,
            'analysis_depth' => $depth,
            'analyzed_at' => now(),
        ]);
    }
}
