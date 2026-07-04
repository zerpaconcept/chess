<?php

namespace App\Models;

use App\Enums\EvaluationType;
use App\Enums\PieceColor;
use Database\Factories\MoveFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $game_id
 * @property int $ply
 * @property int $move_number
 * @property PieceColor $color
 * @property string $san
 * @property string $uci
 * @property string $fen_after
 * @property int|null $evaluation
 * @property EvaluationType|null $evaluation_type
 * @property int|null $eval_loss
 * @property string|null $best_move
 * @property string|null $classification
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'game_id',
    'ply',
    'move_number',
    'color',
    'san',
    'uci',
    'fen_after',
    'evaluation',
    'evaluation_type',
    'eval_loss',
    'best_move',
    'classification',
])]
class Move extends Model
{
    /** @use HasFactory<MoveFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color' => PieceColor::class,
            'evaluation_type' => EvaluationType::class,
        ];
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
