<?php

namespace App\Services\ChessCom;

use App\Enums\ChessComTimeClass;
use App\Enums\GameAnalysisStatus;
use App\Enums\GameColorFilter;
use App\Enums\GameSource;
use App\Exceptions\ChessCom\PlayerNotFoundException;
use App\Models\Game;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class ImportChessComGames
{
    public function __construct(private readonly ChessComClient $client) {}

    /**
     * @param  list<ChessComTimeClass>  $timeClasses
     * @return array{imported: int, skipped: int}
     *
     * @throws PlayerNotFoundException
     */
    public function import(
        User $user,
        string $username,
        CarbonInterface $fromDate,
        CarbonInterface $toDate,
        array $timeClasses,
        GameColorFilter $color,
    ): array {
        $username = strtolower(trim($username));
        $allowedTimeClasses = collect($timeClasses)->map(fn (ChessComTimeClass $timeClass) => $timeClass->value)->all();

        $this->client->getPlayer($username);

        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->endOfDay();

        $imported = 0;
        $skipped = 0;

        foreach ($this->client->getArchiveMonths($username) as $archiveMonth) {
            if (! $this->archiveOverlapsRange($archiveMonth, $from, $to)) {
                continue;
            }

            [$year, $month] = array_map(intval(...), explode('-', $archiveMonth));

            foreach ($this->client->getGamesForMonth($username, $year, $month) as $apiGame) {
                if (! $this->matchesFilters($apiGame, $username, $from, $to, $allowedTimeClasses, $color)) {
                    continue;
                }

                if ($this->storeGame($user, $apiGame)) {
                    $imported++;
                } else {
                    $skipped++;
                }
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  array<string, mixed>  $apiGame
     * @param  list<string>  $allowedTimeClasses
     */
    private function matchesFilters(
        array $apiGame,
        string $username,
        CarbonInterface $from,
        CarbonInterface $to,
        array $allowedTimeClasses,
        GameColorFilter $color,
    ): bool {
        $endTime = isset($apiGame['end_time']) ? Carbon::createFromTimestamp((int) $apiGame['end_time']) : null;

        if ($endTime === null || $endTime->lt($from) || $endTime->gt($to)) {
            return false;
        }

        $timeClass = $apiGame['time_class'] ?? null;

        if (! is_string($timeClass) || ! in_array($timeClass, $allowedTimeClasses, true)) {
            return false;
        }

        $whiteUsername = strtolower((string) data_get($apiGame, 'white.username', ''));
        $blackUsername = strtolower((string) data_get($apiGame, 'black.username', ''));

        return match ($color) {
            GameColorFilter::White => $whiteUsername === $username,
            GameColorFilter::Black => $blackUsername === $username,
            GameColorFilter::Both => $whiteUsername === $username || $blackUsername === $username,
        };
    }

    /**
     * @param  array<string, mixed>  $apiGame
     */
    private function storeGame(User $user, array $apiGame): bool
    {
        $sourceGameId = (string) ($apiGame['url'] ?? $apiGame['uuid'] ?? '');

        if ($sourceGameId === '') {
            return false;
        }

        $pgn = (string) ($apiGame['pgn'] ?? '');
        $headers = $this->parsePgnHeaders($pgn);

        $game = Game::firstOrCreate(
            [
                'source' => GameSource::ChessCom,
                'source_game_id' => $sourceGameId,
            ],
            [
                'user_id' => $user->id,
                'white_player' => (string) data_get($apiGame, 'white.username', 'Unknown'),
                'black_player' => (string) data_get($apiGame, 'black.username', 'Unknown'),
                'white_elo' => data_get($apiGame, 'white.rating'),
                'black_elo' => data_get($apiGame, 'black.rating'),
                'result' => $this->resolveResult($apiGame, $headers),
                'event' => $headers['Event'] ?? null,
                'site' => $headers['Site'] ?? 'Chess.com',
                'played_at' => isset($apiGame['end_time'])
                    ? Carbon::createFromTimestamp((int) $apiGame['end_time'])->toDateString()
                    : ($headers['Date'] ?? null),
                'time_control' => (string) ($apiGame['time_control'] ?? $headers['TimeControl'] ?? ''),
                'eco' => $headers['ECO'] ?? data_get($apiGame, 'eco'),
                'opening' => $headers['Opening'] ?? null,
                'pgn' => $pgn !== '' ? $pgn : null,
                'initial_fen' => $headers['FEN'] ?? null,
                'analysis_status' => GameAnalysisStatus::Pending,
            ],
        );

        if (! $game->wasRecentlyCreated) {
            $game->fill([
                'pgn' => $pgn !== '' ? $pgn : null,
                'initial_fen' => $headers['FEN'] ?? $game->initial_fen,
            ])->save();
        }

        return $game->wasRecentlyCreated;
    }

    /**
     * @return array<string, string>
     */
    private function parsePgnHeaders(string $pgn): array
    {
        $headers = [];

        if (preg_match_all('/^\[(\w+)\s+"([^"]*)"\]/m', $pgn, $matches, PREG_SET_ORDER) === false) {
            return $headers;
        }

        foreach ($matches as $match) {
            $headers[$match[1]] = $match[2];
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $apiGame
     * @param  array<string, string>  $headers
     */
    private function resolveResult(array $apiGame, array $headers): string
    {
        if (isset($headers['Result']) && $headers['Result'] !== '') {
            return $headers['Result'];
        }

        $whiteResult = data_get($apiGame, 'white.result');
        $blackResult = data_get($apiGame, 'black.result');

        if ($whiteResult === 'win') {
            return '1-0';
        }

        if ($blackResult === 'win') {
            return '0-1';
        }

        if (in_array($whiteResult, ['agreed', 'repetition', 'stalemate', 'insufficient'], true)
            || in_array($blackResult, ['agreed', 'repetition', 'stalemate', 'insufficient'], true)) {
            return '1/2-1/2';
        }

        return '*';
    }

    private function archiveOverlapsRange(string $archiveMonth, CarbonInterface $from, CarbonInterface $to): bool
    {
        [$year, $month] = array_map(intval(...), explode('-', $archiveMonth));

        $archiveStart = Carbon::create($year, $month, 1)->startOfMonth();
        $archiveEnd = Carbon::create($year, $month, 1)->endOfMonth();

        return $archiveStart->lte($to) && $archiveEnd->gte($from);
    }
}
