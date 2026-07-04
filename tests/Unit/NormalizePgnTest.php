<?php

namespace Tests\Unit;

use App\Services\Pgn\NormalizePgn;
use PHPUnit\Framework\TestCase;

class NormalizePgnTest extends TestCase
{
    public function test_it_strips_chess_com_clock_comments_from_movetext(): void
    {
        $normalized = (new NormalizePgn)->normalize(<<<'PGN'
[Event "Live Chess"]

1. e4 {[%clk 0:10:00]} e5 {[%clk 0:09:59]} 2. Nf3 1-0
PGN);

        $this->assertStringNotContainsString('[%clk', $normalized);
        $this->assertStringContainsString('1. e4 e5 2. Nf3 1-0', $normalized);
    }
}
