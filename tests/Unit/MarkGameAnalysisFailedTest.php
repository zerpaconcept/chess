<?php

namespace Tests\Unit;

use App\Actions\Analysis\MarkGameAnalysisFailed;
use App\Enums\GameAnalysisStatus;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkGameAnalysisFailedTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_in_progress_game_as_failed(): void
    {
        $game = Game::factory()->create([
            'analysis_status' => GameAnalysisStatus::InProgress,
        ]);

        app(MarkGameAnalysisFailed::class)($game->id);

        $this->assertSame(GameAnalysisStatus::Failed, $game->fresh()->analysis_status);
    }

    public function test_does_not_change_completed_games(): void
    {
        $game = Game::factory()->analyzed()->create();

        app(MarkGameAnalysisFailed::class)($game->id);

        $this->assertSame(GameAnalysisStatus::Completed, $game->fresh()->analysis_status);
    }
}
