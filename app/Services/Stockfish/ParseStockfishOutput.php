<?php

namespace App\Services\Stockfish;

use App\Enums\EvaluationType;
use RuntimeException;

class ParseStockfishOutput
{
    public function parse(string $output, string $fen, int $targetDepth): EngineAnalysis
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

        $bestMove = $bestLine['bestMove'] ?? $this->parseBestMoveLine($output);
        $evaluation = $this->normalizeScoreToWhitePerspective($fen, $bestLine['score'], $bestLine['type']);

        return new EngineAnalysis(
            evaluation: $evaluation,
            evaluationType: $bestLine['type'],
            bestMove: $bestMove,
            depth: min($bestLine['depth'], $targetDepth),
        );
    }

    /**
     * @return array{score: int, type: EvaluationType, bestMove: ?string, depth: int}|null
     */
    private function parseScoreLine(string $line): ?array
    {
        if (! preg_match('/\bdepth (\d+)\b.*\bscore (cp|mate) (-?\d+)\b/', $line, $matches)) {
            return null;
        }

        $bestMove = null;

        if (preg_match('/\bpv (\S+)/', $line, $pvMatches)) {
            $bestMove = $pvMatches[1];
        }

        return [
            'score' => (int) $matches[3],
            'type' => $matches[2] === 'mate' ? EvaluationType::Mate : EvaluationType::Centipawn,
            'bestMove' => $bestMove,
            'depth' => (int) $matches[1],
        ];
    }

    private function parseBestMoveLine(string $output): ?string
    {
        if (! preg_match('/^bestmove (\S+)/m', $output, $matches)) {
            return null;
        }

        return $matches[1] === '(none)' ? null : $matches[1];
    }

    private function normalizeScoreToWhitePerspective(string $fen, int $score, EvaluationType $type): int
    {
        $sideToMove = explode(' ', trim($fen))[1] ?? 'w';

        if ($sideToMove === 'b') {
            return -$score;
        }

        return $score;
    }
}
