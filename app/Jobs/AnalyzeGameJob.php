<?php

namespace App\Jobs;

use App\Enums\GameAnalysisStatus;
use App\Models\Game;
use App\Services\Analysis\AnalyzeGame;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnalyzeGameJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public Game $game) {}

    public function uniqueId(): string
    {
        return (string) $this->game->id;
    }

    public function handle(AnalyzeGame $analyzer): void
    {
        $analyzer->analyze($this->game);
    }

    public function failed(?Throwable $exception): void
    {
        $this->game->update(['analysis_status' => GameAnalysisStatus::Failed]);

        Log::error('Game analysis failed.', [
            'game_id' => $this->game->id,
            'message' => $exception?->getMessage(),
        ]);
    }
}
