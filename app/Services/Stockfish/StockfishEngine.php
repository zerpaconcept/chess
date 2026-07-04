<?php

namespace App\Services\Stockfish;

use RuntimeException;

class StockfishEngine
{
    public function __construct(
        private readonly ?string $binaryPath = null,
        private readonly ?int $movetimeMs = null,
        private readonly ?ParseStockfishOutput $outputParser = null,
    ) {}

    public function analyze(string $fen, int $depth): EngineAnalysis
    {
        $binaryPath = $this->binaryPath ?? config('services.stockfish.path');
        $movetimeMs = $this->movetimeMs ?? config('services.stockfish.movetime_ms', 500);

        if (! is_file($binaryPath)) {
            throw new RuntimeException("Stockfish binary not found at [{$binaryPath}].");
        }

        $process = proc_open(
            [$binaryPath],
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
            $this->write($pipes[0], "go depth {$depth} movetime {$movetimeMs}");

            $output = $this->readUntil($pipes[1], 'bestmove');

            return ($this->outputParser ?? new ParseStockfishOutput)->parse($output, $fen, $depth);
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
}
