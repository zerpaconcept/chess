import { Chess } from 'chess.js';

export const STARTING_FEN =
    'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

export type GamePositionMove = {
    ply: number;
    fen_after: string;
    uci: string;
    san: string;
    best_move: string | null;
};

export function fenForPly(
    ply: number,
    initialFen: string,
    moves: GamePositionMove[],
): string {
    if (ply <= 0) {
        return initialFen;
    }

    const stored = moves.find((move) => move.ply === ply)?.fen_after;

    if (stored) {
        return stored;
    }

    return replayMovesToFen(initialFen, moves, ply);
}

function replayMovesToFen(
    initialFen: string,
    moves: GamePositionMove[],
    ply: number,
): string {
    const chess = new Chess(initialFen);

    for (const move of moves) {
        if (move.ply > ply) {
            break;
        }

        const squares = uciSquares(move.uci);

        if (!squares) {
            break;
        }

        chess.move({
            from: squares.from,
            to: squares.to,
            promotion: move.uci.length > 4 ? move.uci[4] : undefined,
        });
    }

    return chess.fen();
}

export function moveForPly(
    ply: number,
    moves: GamePositionMove[],
): GamePositionMove | null {
    if (ply <= 0) {
        return null;
    }

    return moves.find((move) => move.ply === ply) ?? null;
}

export function uciSquares(
    uci: string,
): { from: string; to: string } | null {
    if (uci.length < 4) {
        return null;
    }

    return {
        from: uci.slice(0, 2),
        to: uci.slice(2, 4),
    };
}

export function uciToArrow(uci: string): {
    startSquare: string;
    endSquare: string;
    color: string;
} | null {
    const squares = uciSquares(uci);

    if (!squares) {
        return null;
    }

    return {
        startSquare: squares.from,
        endSquare: squares.to,
        color: 'rgba(34, 197, 94, 0.75)',
    };
}
