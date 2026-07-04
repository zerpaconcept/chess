import { Button } from '@/components/ui/button';
import {
    fenForPly,
    moveForPly,
    uciSquares,
    uciToArrow,
    type GamePositionMove,
} from '@/lib/game-position';
import { cn } from '@/lib/utils';
import {
    ChevronFirst,
    ChevronLast,
    ChevronLeft,
    ChevronRight,
    FlipVertical,
} from 'lucide-react';
import type { CSSProperties } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { Chessboard } from 'react-chessboard';

type GameBoardProps = {
    initialFen: string;
    moves: GamePositionMove[];
    selectedPly: number;
    onSelectPly: (ply: number) => void;
    className?: string;
};

export default function GameBoard({
    initialFen,
    moves,
    selectedPly,
    onSelectPly,
    className,
}: GameBoardProps) {
    const [mounted, setMounted] = useState(false);
    const [boardOrientation, setBoardOrientation] = useState<'white' | 'black'>(
        'white',
    );

    const maxPly = moves.at(-1)?.ply ?? 0;
    const currentMove = moveForPly(selectedPly, moves);
    const position = fenForPly(selectedPly, initialFen, moves);

    useEffect(() => {
        setMounted(true);
    }, []);

    useEffect(() => {
        function handleKeyDown(event: KeyboardEvent): void {
            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                onSelectPly(Math.max(0, selectedPly - 1));
            }

            if (event.key === 'ArrowRight') {
                event.preventDefault();
                onSelectPly(Math.min(maxPly, selectedPly + 1));
            }

            if (event.key === 'Home') {
                event.preventDefault();
                onSelectPly(0);
            }

            if (event.key === 'End') {
                event.preventDefault();
                onSelectPly(maxPly);
            }
        }

        window.addEventListener('keydown', handleKeyDown);

        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [maxPly, onSelectPly, selectedPly]);

    const boardOptions = useMemo(() => {
        const lastMoveSquares = currentMove ? uciSquares(currentMove.uci) : null;
        const bestMoveArrow =
            currentMove?.best_move &&
            currentMove.best_move !== currentMove.uci
                ? uciToArrow(currentMove.best_move)
                : null;

        const squareStyles: Record<string, CSSProperties> = {};

        if (lastMoveSquares) {
            squareStyles[lastMoveSquares.from] = {
                backgroundColor: 'rgba(255, 255, 0, 0.35)',
            };
            squareStyles[lastMoveSquares.to] = {
                backgroundColor: 'rgba(255, 255, 0, 0.55)',
            };
        }

        return {
            position,
            boardOrientation,
            allowDragging: false,
            showAnimations: true,
            animationDurationInMs: 120,
            showNotation: true,
            darkSquareStyle: { backgroundColor: '#769656' },
            lightSquareStyle: { backgroundColor: '#eeeed2' },
            squareStyles,
            arrows: bestMoveArrow ? [bestMoveArrow] : [],
        };
    }, [boardOrientation, currentMove, position]);

    return (
        <div className={cn('space-y-4', className)}>
            <div className="mx-auto aspect-square w-full max-w-[420px] overflow-hidden rounded-lg border bg-muted/20 shadow-sm">
                {mounted ? (
                    <Chessboard options={boardOptions} />
                ) : (
                    <div className="flex h-full w-full items-center justify-center text-sm text-muted-foreground">
                        Loading board...
                    </div>
                )}
            </div>

            <div className="flex flex-col gap-3">
                <div className="flex items-center justify-between gap-2 text-sm">
                    <span className="text-muted-foreground">
                        Move {selectedPly} / {maxPly}
                    </span>
                    <span className="truncate font-medium">
                        {selectedPly === 0
                            ? 'Starting position'
                            : currentMove?.san}
                    </span>
                </div>

                <div className="flex flex-wrap items-center justify-center gap-2">
                    <Button
                        type="button"
                        size="icon"
                        variant="outline"
                        disabled={selectedPly <= 0}
                        onClick={() => onSelectPly(0)}
                        aria-label="First move"
                    >
                        <ChevronFirst className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="outline"
                        disabled={selectedPly <= 0}
                        onClick={() => onSelectPly(Math.max(0, selectedPly - 1))}
                        aria-label="Previous move"
                    >
                        <ChevronLeft className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="outline"
                        disabled={selectedPly >= maxPly}
                        onClick={() =>
                            onSelectPly(Math.min(maxPly, selectedPly + 1))
                        }
                        aria-label="Next move"
                    >
                        <ChevronRight className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="outline"
                        disabled={selectedPly >= maxPly}
                        onClick={() => onSelectPly(maxPly)}
                        aria-label="Last move"
                    >
                        <ChevronLast className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        onClick={() =>
                            setBoardOrientation((orientation) =>
                                orientation === 'white' ? 'black' : 'white',
                            )
                        }
                        aria-label="Flip board"
                    >
                        <FlipVertical className="size-4" />
                    </Button>
                </div>
            </div>
        </div>
    );
}
