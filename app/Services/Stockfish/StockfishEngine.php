<?php

namespace App\Services\Stockfish;

use App\Enums\EvaluationType;
use RuntimeException;

class StockfishEngine
{
    private readonly string $binaryPath;

    private readonly int $movetimeMs;

    public function __construct(?string $binaryPath = null, ?int $movetimeMs = null)
    {
        $this->binaryPath = $binaryPath ?? config('services.stockfish.path');
        $this->movetimeMs = $movetimeMs ?? config('services.stockfish.movetime_ms', 500);
    }

    public function analyze(string $fen, int $depth): EngineAnalysis
    {
        if (! is_file($this->binaryPath)) {
            throw new RuntimeException("Stockfish binary not found at [{$this->binaryPath}].");
        }

        $process = proc_open(
            [$this->binaryPath],
            [
                ['pipe', 'r'],
                ['pipe', 'w'],
                ['pipe', 'w'],
            ],
            $pipes,
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Unable to start Stockfish process.');
        }

        try {
            $this->write($pipes[0], 'uci');
            $this->readUntil($pipes[1], 'uciok');

            $this->write($pipes[0], 'isready');
            $this->readUntil($pipes[1], 'readyok');

            $this->write($pipes[0], "position fen {$fen}");
            $this->write($pipes[0], "go depth {$depth} movetime {$this->movetimeMs}");

            $output = $this->readUntil($pipes[1], 'bestmove');

            return $this->parseAnalysis($output, $fen, $depth);
        } finally {
            $this->write($pipes[0], 'quit');
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }
    }

    /**
     * @param  resource  $stdin
     */
    private function write($stdin, string $command): void
    {
        fwrite($stdin, $command."\n");
    }

    /**
     * @param  resource  $stdout
     */
    private function readUntil($stdout, string $needle): string
    {
        $output = '';
        $deadline = microtime(true) + config('services.stockfish.timeout_seconds', 30);

        while (microtime(true) < $deadline) {
            $line = fgets($stdout);

            if ($line === false) {
                usleep(10_000);

                continue;
            }

            $output .= $line;

            if (str_contains($line, $needle)) {
                return $output;
            }
        }

        throw new RuntimeException("Timed out waiting for Stockfish response containing [{$needle}].");
    }

    /**
     * @return array{score: int, type: EvaluationType, bestMove: ?string, depth: int}|null
     */
    private function parseScoreLine(string $line): ?array
    {
        if (! preg_match('/depth (\d+).*score (cp|mate) (-?\d+).* pv (\S+)/', $line, $matches)) {
            return null;
        }

        $score = (int) $matches[3];
        $type = $matches[2] === 'mate' ? EvaluationType::Mate : EvaluationType::Centipawn;

        return [
            'score' => $score,
            'type' => $type,
            'bestMove' => $matches[4],
            'depth' => (int) $matches[1],
        ];
    }

    private function parseAnalysis(string $output, string $fen, int $targetDepth): EngineAnalysis
    {
        $bestLine = null;

        foreach (explode("\n", $output) as $line) {
            if (! str_starts_with(trim($line), 'info')) {
                continue;
            }

            $parsed = $this->parseScoreLine($line);

            if ($parsed === null) {
                continue;
            }

            if ($bestLine === null || $parsed['depth'] >= $bestLine['depth']) {
                $bestLine = $parsed;
            }
        }

        if ($bestLine === null) {
            throw new RuntimeException('Stockfish did not return an evaluation.');
        }

        $evaluation = $this->normalizeScoreToWhitePerspective($fen, $bestLine['score'], $bestLine['type']);

        return new EngineAnalysis(
            evaluation: $evaluation,
            evaluationType: $bestLine['type'],
            bestMove: $bestLine['bestMove'],
            depth: min($bestLine['depth'], $targetDepth),
        );
    }

    private function normalizeScoreToWhitePerspective(string $fen, int $score, EvaluationType $type): int
    {
        $sideToMove = explode(' ', trim($fen))[1] ?? 'w';

        if ($sideToMove === 'b') {
            return $type === EvaluationType::Mate ? -$score : -$score;
        }

        return $score;
    }
}
