<?php

namespace App\Services\ChessCom;

use App\Exceptions\ChessCom\PlayerNotFoundException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

class ChessComClient
{
    private readonly string $baseUrl;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? config('services.chess_com.base_url'), '/');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws PlayerNotFoundException
     * @throws ConnectionException
     * @throws RequestException
     */
    public function getPlayer(string $username): array
    {
        $response = $this->request("/player/{$username}");

        if ($response->status() === 404) {
            throw new PlayerNotFoundException($username);
        }

        $response->throw();

        return $response->json();
    }

    /**
     * @return list<string> Archive keys in YYYY-MM format.
     *
     * @throws PlayerNotFoundException
     * @throws ConnectionException
     * @throws RequestException
     */
    public function getArchiveMonths(string $username): array
    {
        $response = $this->request("/player/{$username}/games/archives");

        if ($response->status() === 404) {
            throw new PlayerNotFoundException($username);
        }

        $response->throw();

        /** @var list<string> $archives */
        $archives = $response->json('archives', []);

        return collect($archives)
            ->map(fn (string $archiveUrl): ?string => $this->parseArchiveMonth($archiveUrl))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function getGamesForMonth(string $username, int $year, int $month): array
    {
        $month = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        $response = $this->request("/player/{$username}/games/{$year}/{$month}");

        $response->throw();

        /** @var list<array<string, mixed>> $games */
        $games = $response->json('games', []);

        return $games;
    }

    /**
     * @throws ConnectionException
     */
    private function request(string $path): Response
    {
        Sleep::usleep(config('services.chess_com.request_delay_microseconds', 200_000));

        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout(config('services.chess_com.timeout', 15))
            ->connectTimeout(config('services.chess_com.connect_timeout', 5))
            ->retry(
                config('services.chess_com.retry_times', 2),
                config('services.chess_com.retry_sleep_milliseconds', 500),
                throw: false,
            )
            ->get($path);
    }

    private function parseArchiveMonth(string $archiveUrl): ?string
    {
        if (preg_match('#/games/(\d{4})/(\d{2})$#', $archiveUrl, $matches) !== 1) {
            return null;
        }

        return "{$matches[1]}-{$matches[2]}";
    }
}
