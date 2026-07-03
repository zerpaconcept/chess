<?php

namespace App\Models;

use App\Enums\GameAnalysisStatus;
use App\Enums\GameSource;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property GameSource $source
 * @property string|null $source_game_id
 * @property string $white_player
 * @property string $black_player
 * @property int|null $white_elo
 * @property int|null $black_elo
 * @property string $result
 * @property string|null $event
 * @property string|null $site
 * @property Carbon|null $played_at
 * @property string|null $time_control
 * @property string|null $eco
 * @property string|null $opening
 * @property string|null $pgn
 * @property string|null $initial_fen
 * @property GameAnalysisStatus $analysis_status
 * @property int|null $analysis_depth
 * @property Carbon|null $analyzed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'source',
    'source_game_id',
    'white_player',
    'black_player',
    'white_elo',
    'black_elo',
    'result',
    'event',
    'site',
    'played_at',
    'time_control',
    'eco',
    'opening',
    'pgn',
    'initial_fen',
    'analysis_status',
    'analysis_depth',
    'analyzed_at',
])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => GameSource::class,
            'played_at' => 'date',
            'analysis_status' => GameAnalysisStatus::class,
            'analyzed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Move, $this>
     */
    public function moves(): HasMany
    {
        return $this->hasMany(Move::class)->orderBy('ply');
    }
}
