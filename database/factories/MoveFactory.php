<?php

namespace Database\Factories;

use App\Enums\EvaluationType;
use App\Enums\PieceColor;
use App\Models\Game;
use App\Models\Move;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Move>
 */
class MoveFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ply = fake()->numberBetween(1, 40);
        $moveNumber = (int) ceil($ply / 2);

        return [
            'game_id' => Game::factory(),
            'ply' => $ply,
            'move_number' => $moveNumber,
            'color' => $ply % 2 === 1 ? PieceColor::White : PieceColor::Black,
            'san' => 'e4',
            'uci' => 'e2e4',
            'fen_after' => 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1',
            'evaluation' => null,
            'evaluation_type' => null,
            'eval_loss' => null,
            'best_move' => null,
            'classification' => null,
        ];
    }

    public function withEvaluation(int $centipawns): static
    {
        return $this->state(fn (array $attributes) => [
            'evaluation' => $centipawns,
            'evaluation_type' => EvaluationType::Centipawn,
            'best_move' => 'e2e4',
        ]);
    }
}
