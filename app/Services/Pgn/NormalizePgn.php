<?php

namespace App\Services\Pgn;

class NormalizePgn
{
    public function normalize(string $pgn): string
    {
        if (trim($pgn) === '') {
            return $pgn;
        }

        if (preg_match('/^((?:\s*\[[^\]\n]+\]\s*)+)(.*)$/us', $pgn, $matches) !== 1) {
            return $this->normalizeMovetext($pgn);
        }

        return trim($matches[1])."\n\n".$this->normalizeMovetext(trim($matches[2]));
    }

    private function normalizeMovetext(string $movetext): string
    {
        $movetext = preg_replace('/\{[^}]*\}/', ' ', $movetext) ?? $movetext;
        $movetext = preg_replace('/;[^\n]*/', ' ', $movetext) ?? $movetext;
        $movetext = preg_replace('/\$\d+/', ' ', $movetext) ?? $movetext;
        $movetext = preg_replace('/\s+/', ' ', $movetext) ?? $movetext;

        return trim($movetext);
    }
}
