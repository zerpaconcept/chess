<?php

namespace App\Services\Pgn;

use App\Enums\PieceColor;
use App\Models\Game;
use Cmuset\ChessTools\Enum\CastlingEnum;
use Cmuset\ChessTools\Enum\ColorEnum;
use Cmuset\ChessTools\Enum\CoordinatesEnum;
use Cmuset\ChessTools\Enum\PieceEnum;
use Cmuset\ChessTools\Model\Game as ParsedGame;
use Cmuset\ChessTools\Model\Move as ParsedMove;
use Cmuset\ChessTools\Model\Position;
use Cmuset\ChessTools\Tool\MoveApplier\Exception\MoveApplyingException;
use Cmuset\ChessTools\Tool\Parser\PGNParser;
use Cmuset\ChessTools\Tool\Resolver\MoveResolver;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StoreGameMoves
{
    public function __construct(private readonly NormalizePgn $normalizePgn) {}

    /**
     * Parse the game's PGN and persist its main-line moves.
     *
     * @return int Number of moves stored.
     */
    public function store(Game $game, ?string $expectedFinalFen = null): int
    {
        if (blank($game->pgn)) {
            return 0;
        }

        $normalizedPgn = $this->normalizePgn->normalize($game->pgn);
        $parsedGame = ParsedGame::fromPGN($normalizedPgn);

        $initialPosition = $parsedGame->getInitialPosition() ?? Position::fromFEN(PGNParser::INITIAL_FEN);
        $initialFen = $initialPosition->getFEN();

        if ($game->initial_fen !== $initialFen) {
            $game->forceFill(['initial_fen' => $initialFen])->save();
        }

        $rows = $this->buildMoveRows($game, $parsedGame, clone $initialPosition);

        if ($rows === []) {
            return 0;
        }

        if ($expectedFinalFen !== null && ! $this->fensMatch($rows[count($rows) - 1]['fen_after'], $expectedFinalFen)) {
            throw new RuntimeException(
                "PGN replay does not match the expected final position for game [{$game->id}]."
            );
        }

        DB::transaction(function () use ($game, $rows): void {
            $game->moves()->delete();
            $game->moves()->createMany($rows);
        });

        return count($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildMoveRows(Game $game, ParsedGame $parsedGame, Position $position): array
    {
        $rows = [];
        $ply = 0;

        foreach ($parsedGame->getMainLine() as $node) {
            $move = $node->getMove();

            if ($move === null) {
                continue;
            }

            $before = clone $position;
            $san = $move->getSAN();

            try {
                $parsedMove = ParsedMove::fromSAN($this->cleanSanForParsing($san), $before->getSideToMove());
                $resolvedMove = $this->resolveLegalMove($before, $parsedMove);
            } catch (MoveApplyingException|RuntimeException $exception) {
                throw new RuntimeException(
                    "Failed to parse move [{$san}] at ply ".($ply + 1)." for game [{$game->id}] "
                    ."(FEN: {$before->getFEN()}): {$exception->getMessage()}",
                    previous: $exception,
                );
            }

            $after = clone $before;
            $after->applyMove($resolvedMove);

            $rows[] = [
                'game_id' => $game->id,
                'ply' => ++$ply,
                'move_number' => $node->getMoveNumber() ?? (int) ceil($ply / 2),
                'color' => $this->mapColor($before->getSideToMove())->value,
                'san' => $san,
                'uci' => $this->resolveUci($resolvedMove, $before, $after),
                'fen_after' => $after->getFEN(),
                'evaluation' => null,
                'evaluation_type' => null,
                'best_move' => null,
                'classification' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $position = $after;
        }

        return $rows;
    }

    private function cleanSanForParsing(string $san): string
    {
        $san = trim($san);

        return preg_replace('/(?<=[a-h1-8])(?:!!|!\?|\?!|\?\?|!|\?|\+|#)+$/', '', $san) ?? $san;
    }

    private function resolveLegalMove(Position $position, ParsedMove $move): ParsedMove
    {
        if ($move->isCastling()) {
            MoveResolver::create()->resolve($position, $move);

            return $move;
        }

        $matches = $this->findMatchingLegalMoves($position, $move);
        $matches = $this->narrowLegalMatches($position, $move, $matches);

        if (count($matches) === 1) {
            return $matches[0];
        }

        if (count($matches) === 0) {
            $move = $this->clearDisambiguation($move);
            $matches = $this->narrowLegalMatches($position, $move, $this->findMatchingLegalMoves($position, $move));

            if (count($matches) === 1) {
                return $matches[0];
            }

            try {
                MoveResolver::create()->resolve($position, $move);

                return $move;
            } catch (MoveApplyingException $exception) {
                throw new RuntimeException(
                    "No legal move matches [{$move->getSAN()}].",
                    previous: $exception,
                );
            }
        }

        throw new RuntimeException("Multiple legal moves match [{$move->getSAN()}].");
    }

    /**
     * @return list<ParsedMove>
     */
    private function findMatchingLegalMoves(Position $position, ParsedMove $move): array
    {
        return array_values(array_filter(
            $position->getLegalMoves(),
            fn (ParsedMove $legal) => $this->matchesParsedMove($move, $legal),
        ));
    }

    private function clearDisambiguation(ParsedMove $move): ParsedMove
    {
        $move->setSquareFrom(null);
        $move->setFileFrom(null);
        $move->setRowFrom(null);

        return $move;
    }

    /**
     * @param  list<ParsedMove>  $matches
     * @return list<ParsedMove>
     */
    private function narrowLegalMatches(Position $position, ParsedMove $move, array $matches): array
    {
        if (count($matches) <= 1) {
            return $matches;
        }

        if ($move->isCapture()) {
            $matches = array_values(array_filter($matches, fn (ParsedMove $legal) => $legal->isCapture()));
        }

        if (count($matches) <= 1) {
            return $matches;
        }

        if ($move->isCheckmate() || $move->isCheck()) {
            $matches = array_values(array_filter($matches, function (ParsedMove $legal) use ($position, $move) {
                $after = clone $position;
                $after->applyMove($legal);

                if ($move->isCheckmate()) {
                    return $after->isCheckmate();
                }

                return $after->isCheck();
            }));
        }

        return $matches;
    }

    private function matchesParsedMove(ParsedMove $parsed, ParsedMove $legal): bool
    {
        if ($parsed->isCastling()) {
            return $legal->isCastling() && $parsed->getCastling() === $legal->getCastling();
        }

        if ($parsed->getTo() !== $legal->getTo()) {
            return false;
        }

        $parsedPiece = $parsed->getPiece();
        $legalPiece = $legal->getPiece();

        if ($parsedPiece === null || $legalPiece === null) {
            return false;
        }

        if ($this->pieceKind($parsedPiece) !== $this->pieceKind($legalPiece)) {
            return false;
        }

        if ($parsed->getFileFrom() !== null
            && $legal->getSquareFrom()?->file() !== $parsed->getFileFrom()) {
            return false;
        }

        if ($parsed->getRankFrom() !== null
            && $legal->getSquareFrom()?->rank() !== $parsed->getRankFrom()) {
            return false;
        }

        if ($parsed->getSquareFrom() !== null
            && $legal->getSquareFrom() !== $parsed->getSquareFrom()) {
            return false;
        }

        if ($parsed->getPromotion() !== null
            && $parsed->getPromotion() !== $legal->getPromotion()) {
            return false;
        }

        return true;
    }

    private function pieceKind(PieceEnum $piece): string
    {
        return strtoupper($piece->value);
    }

    private function mapColor(ColorEnum $color): PieceColor
    {
        return $color === ColorEnum::WHITE ? PieceColor::White : PieceColor::Black;
    }

    private function fensMatch(string $left, string $right): bool
    {
        return $this->normalizeFenForComparison($left) === $this->normalizeFenForComparison($right);
    }

    private function normalizeFenForComparison(string $fen): string
    {
        $parts = explode(' ', trim($fen));

        return implode(' ', array_slice($parts, 0, 4));
    }

    private function resolveUci(ParsedMove $move, Position $before, Position $after): string
    {
        if ($move->isCastling()) {
            return match ($move->getCastling()) {
                CastlingEnum::WHITE_KINGSIDE => 'e1g1',
                CastlingEnum::WHITE_QUEENSIDE => 'e1c1',
                CastlingEnum::BLACK_KINGSIDE => 'e8g8',
                CastlingEnum::BLACK_QUEENSIDE => 'e8c8',
                default => throw new RuntimeException('Unable to resolve UCI for castling move.'),
            };
        }

        $from = $move->getSquareFrom()?->value;
        $to = $move->getTo()?->value;

        if ($from !== null && $to !== null) {
            return $this->formatUci($from, $to, $move);
        }

        return $this->inferUciFromPositions($move, $before, $after);
    }

    private function inferUciFromPositions(ParsedMove $move, Position $before, Position $after): string
    {
        $to = $move->getTo()?->value;
        $from = null;
        $movingPiece = $move->getPiece();

        if ($movingPiece !== null) {
            foreach (CoordinatesEnum::cases() as $square) {
                $pieceBefore = $before->getPieceAt($square);
                $pieceAfter = $after->getPieceAt($square);

                if ($pieceBefore === $movingPiece && $pieceAfter !== $movingPiece) {
                    $from = $square->value;
                }

                if ($to === null && $pieceAfter === $movingPiece && $pieceBefore !== $movingPiece) {
                    $to = $square->value;
                }
            }
        }

        if ($from === null && $to !== null && $move->getPromotion() !== null) {
            $color = $move->getPiece()?->color();

            foreach (CoordinatesEnum::cases() as $square) {
                $pieceBefore = $before->getPieceAt($square);
                $pieceAfter = $after->getPieceAt($square);

                if ($pieceBefore !== null
                    && $pieceBefore->isPawn()
                    && $color !== null
                    && $pieceBefore->color() === $color
                    && $pieceAfter !== $pieceBefore) {
                    $from = $square->value;

                    break;
                }
            }
        }

        if ($from === null || $to === null) {
            throw new RuntimeException('Unable to resolve UCI coordinates for move.');
        }

        return $this->formatUci($from, $to, $move);
    }

    private function formatUci(string $from, string $to, ParsedMove $move): string
    {
        $uci = $from.$to;

        $promotion = $move->getPromotion();

        if ($promotion !== null) {
            $uci .= strtolower(substr($promotion->value, -1));
        }

        return $uci;
    }
}
