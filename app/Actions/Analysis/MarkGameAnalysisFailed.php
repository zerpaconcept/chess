<?php

namespace App\Actions\Analysis;

use App\Enums\GameAnalysisStatus;
use App\Models\Game;
use Illuminate\Support\Facades\Log;
use Throwable;

class MarkGameAnalysisFailed
{
    public function __invoke(int $gameId, ?Throwable $exception = null): void
    {
        $updated = Game::query()
            ->whereKey($gameId)
            ->where('analysis_status', GameAnalysisStatus::InProgress)
            ->update(['analysis_status' => GameAnalysisStatus::Failed]);

        if ($updated === 0) {
            return;
        }

        Log::error('Game analysis failed.', [
            'game_id' => $gameId,
            'message' => $exception?->getMessage(),
        ]);
    }
}
