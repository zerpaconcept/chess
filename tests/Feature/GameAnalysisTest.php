<?php

namespace Tests\Feature;

use App\Enums\EvaluationType;
use App\Enums\GameAnalysisStatus;
use App\Jobs\AnalyzeGameJob;
use App\Models\Game;
use App\Models\Move;
use App\Models\User;
use App\Services\Analysis\AnalyzeGame;
use App\Services\Stockfish\EngineAnalysis;
use App\Services\Stockfish\StockfishEngine;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class GameAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_queue_analysis_for_their_game(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create([
            'pgn' => "[Event \"Test\"]\n\n1. e4 e5 1-0\n",
            'analysis_status' => GameAnalysisStatus::Pending,
        ]);

        $this->actingAs($user)
            ->post(route('games.analyze', $game))
            ->assertRedirect();

        Queue::assertPushed(AnalyzeGameJob::class, fn (AnalyzeGameJob $job) => $job->game->is($game));

        $this->assertSame(GameAnalysisStatus::Pending, $game->fresh()->analysis_status);
    }

    public function test_failed_analysis_job_marks_game_as_failed(): void
    {
        $game = Game::factory()->create([
            'pgn' => "[Event \"Test\"]\n\n1. e4 e5 1-0\n",
            'analysis_status' => GameAnalysisStatus::InProgress,
        ]);

        (new AnalyzeGameJob($game))->failed(new \RuntimeException('Worker timed out.'));

        $this->assertSame(GameAnalysisStatus::Failed, $game->fresh()->analysis_status);
    }

    public function test_job_failed_event_marks_game_as_failed(): void
    {
        $game = Game::factory()->create([
            'analysis_status' => GameAnalysisStatus::InProgress,
        ]);

        $job = new AnalyzeGameJob($game);
        $payload = [
            'displayName' => AnalyzeGameJob::class,
            'data' => [
                'command' => serialize($job),
            ],
        ];

        $queueJob = $this->createMock(Job::class);
        $queueJob->method('payload')->willReturn($payload);

        event(new JobFailed(
            'database',
            $queueJob,
            new \RuntimeException('Worker timed out.'),
        ));

        $this->assertSame(GameAnalysisStatus::Failed, $game->fresh()->analysis_status);
    }

    public function test_user_cannot_analyze_another_users_game(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $game = Game::factory()->for($owner)->create([
            'pgn' => "[Event \"Test\"]\n\n1. e4 e5 1-0\n",
        ]);

        $this->actingAs($otherUser)
            ->post(route('games.analyze', $game))
            ->assertForbidden();
    }

    public function test_analysis_parses_moves_and_stores_evaluations(): void
    {
        $this->mock(StockfishEngine::class, function (MockInterface $mock): void {
            $mock->shouldReceive('analyze')
                ->times(3)
                ->andReturn(
                    new EngineAnalysis(0, EvaluationType::Centipawn, 'e2e4', 18),
                    new EngineAnalysis(25, EvaluationType::Centipawn, 'e2e4', 18),
                    new EngineAnalysis(15, EvaluationType::Centipawn, 'e7e5', 18),
                );
        });

        $game = Game::factory()->create([
            'pgn' => "[Event \"Test\"]\n\n1. e4 e5 1-0\n",
            'analysis_status' => GameAnalysisStatus::Pending,
        ]);

        app(AnalyzeGame::class)->analyze($game);

        $game->refresh();

        $this->assertSame(GameAnalysisStatus::Completed, $game->analysis_status);
        $this->assertSame(18, $game->analysis_depth);
        $this->assertNotNull($game->analyzed_at);
        $this->assertSame(2, Move::query()->where('game_id', $game->id)->count());

        $firstMove = Move::query()->where('game_id', $game->id)->where('ply', 1)->first();
        $secondMove = Move::query()->where('game_id', $game->id)->where('ply', 2)->first();

        $this->assertNotNull($firstMove);
        $this->assertSame(25, $firstMove->evaluation);
        $this->assertSame(EvaluationType::Centipawn, $firstMove->evaluation_type);
        $this->assertSame('e2e4', $firstMove->best_move);
        $this->assertSame(-25, $firstMove->eval_loss);

        $this->assertNotNull($secondMove);
        $this->assertSame(15, $secondMove->evaluation);
        $this->assertSame(-10, $secondMove->eval_loss);
    }

    public function test_import_does_not_parse_moves(): void
    {
        $game = Game::factory()->create([
            'pgn' => "[Event \"Test\"]\n\n1. e4 e5 1-0\n",
        ]);

        $this->assertSame(0, Move::query()->where('game_id', $game->id)->count());
    }
}
