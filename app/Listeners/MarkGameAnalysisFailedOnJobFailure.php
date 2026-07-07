<?php

namespace App\Listeners;

use App\Actions\Analysis\MarkGameAnalysisFailed;
use App\Jobs\AnalyzeGameJob;
use Illuminate\Queue\Events\JobFailed;
use Throwable;

class MarkGameAnalysisFailedOnJobFailure
{
    public function __construct(
        private readonly MarkGameAnalysisFailed $markGameAnalysisFailed,
    ) {}

    public function handle(JobFailed $event): void
    {
        $gameId = $this->resolveGameId($event);

        if ($gameId === null) {
            return;
        }

        ($this->markGameAnalysisFailed)($gameId, $event->exception);
    }

    private function resolveGameId(JobFailed $event): ?int
    {
        $payload = $event->job->payload();

        if (($payload['displayName'] ?? null) !== AnalyzeGameJob::class) {
            return null;
        }

        try {
            $command = unserialize($payload['data']['command'], ['allowed_classes' => true]);
        } catch (Throwable) {
            return null;
        }

        if (! $command instanceof AnalyzeGameJob) {
            return null;
        }

        return $command->game->getKey();
    }
}
