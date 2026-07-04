import GameController from '@/actions/App/Http/Controllers/GameController';
import EvaluationGraph from '@/components/games/evaluation-graph';
import GameBoard from '@/components/games/game-board';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    formatEvalLoss,
    formatEvaluation,
    formatMoveLabel,
    type EvaluationPoint,
} from '@/lib/chess-evaluation';
import type { GamePositionMove } from '@/lib/game-position';
import { cn } from '@/lib/utils';
import { index as gamesIndex, show as gamesShow } from '@/routes/games';
import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Cpu } from 'lucide-react';
import { useMemo, useState } from 'react';

type GameReview = {
    id: number;
    white_player: string;
    black_player: string;
    white_elo: number | null;
    black_elo: number | null;
    result: string;
    source: string;
    played_at: string | null;
    opening: string | null;
    eco: string | null;
    event: string | null;
    time_control: string | null;
    initial_fen: string;
    analysis_status: 'pending' | 'in_progress' | 'completed' | 'failed';
    analysis_depth: number | null;
    analyzed_at: string | null;
    can_analyze: boolean;
};

type MoveReview = GamePositionMove & {
    id: number;
    move_number: number;
    color: string;
    evaluation: number | null;
    evaluation_type: 'cp' | 'mate' | null;
    eval_loss: number | null;
    classification: string | null;
};

const statusLabels: Record<GameReview['analysis_status'], string> = {
    pending: 'Pending',
    in_progress: 'Analyzing',
    completed: 'Analyzed',
    failed: 'Failed',
};

function formatPlayers(game: GameReview): string {
    const white = game.white_elo
        ? `${game.white_player} (${game.white_elo})`
        : game.white_player;
    const black = game.black_elo
        ? `${game.black_player} (${game.black_elo})`
        : game.black_player;

    return `${white} vs ${black}`;
}

export default function GameShow({
    game,
    moves,
}: {
    game: GameReview;
    moves: MoveReview[];
}) {
    const maxPly = moves.at(-1)?.ply ?? 0;
    const [selectedPly, setSelectedPly] = useState<number>(maxPly);

    const evaluationPoints = useMemo(
        (): EvaluationPoint[] =>
            moves.flatMap((move) =>
                move.evaluation !== null && move.evaluation_type !== null
                    ? [
                          {
                              ply: move.ply,
                              evaluation: move.evaluation,
                              evaluationType: move.evaluation_type,
                          },
                      ]
                    : [],
            ),
        [moves],
    );

    const hasAnalysis = evaluationPoints.length > 0;

    setLayoutProps({
        breadcrumbs: [
            {
                title: 'Games',
                href: gamesIndex(),
            },
            {
                title: `${game.white_player} vs ${game.black_player}`,
                href: gamesShow(game.id),
            },
        ],
    });

    function analyze(): void {
        router.post(GameController.analyze.url(game.id), {}, { preserveScroll: true });
    }

    return (
        <>
            <Head title={`${game.white_player} vs ${game.black_player}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-3">
                        <Link
                            href={gamesIndex()}
                            className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft className="size-4" />
                            Back to games
                        </Link>

                        <Heading
                            title={formatPlayers(game)}
                            description={
                                game.opening ??
                                game.event ??
                                'Review engine evaluation move by move.'
                            }
                        />

                        <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <Badge variant="outline">{game.result}</Badge>
                            <Badge variant="secondary">
                                {statusLabels[game.analysis_status]}
                            </Badge>
                            {game.played_at && <span>{game.played_at}</span>}
                            {game.time_control && <span>{game.time_control}</span>}
                            {game.eco && <span>{game.eco}</span>}
                        </div>
                    </div>

                    <Button
                        variant="outline"
                        disabled={!game.can_analyze}
                        onClick={analyze}
                    >
                        <Cpu className="size-4" />
                        {game.analysis_status === 'in_progress'
                            ? 'Analyzing...'
                            : game.analysis_status === 'completed'
                              ? 'Re-analyze'
                              : 'Analyze'}
                    </Button>
                </div>

                {moves.length === 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>No moves yet</CardTitle>
                            <CardDescription>
                                Run analysis to parse the PGN and generate
                                evaluations for this game.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <div className="grid gap-6 xl:grid-cols-[minmax(0,420px)_minmax(0,1fr)]">
                        <div className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Board</CardTitle>
                                    <CardDescription>
                                        Step through the game with arrow keys.
                                        Green arrow shows the engine best move.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <GameBoard
                                        initialFen={game.initial_fen}
                                        moves={moves}
                                        selectedPly={selectedPly}
                                        onSelectPly={setSelectedPly}
                                    />
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Evaluation</CardTitle>
                                    <CardDescription>
                                        {hasAnalysis
                                            ? 'White advantage by move. Positive values favor White.'
                                            : 'Analyze this game to populate the evaluation graph.'}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <EvaluationGraph
                                        points={evaluationPoints}
                                        selectedPly={
                                            selectedPly > 0 ? selectedPly : null
                                        }
                                        onSelectPly={setSelectedPly}
                                    />
                                </CardContent>
                            </Card>
                        </div>

                        <Card className="flex min-h-[420px] flex-col xl:min-h-0">
                            <CardHeader>
                                <CardTitle>Moves</CardTitle>
                                <CardDescription>
                                    {moves.length} moves
                                    {game.analysis_depth
                                        ? ` · depth ${game.analysis_depth}`
                                        : ''}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex-1 overflow-hidden">
                                <div className="max-h-[720px] overflow-y-auto pr-1">
                                    <table className="w-full text-sm">
                                        <thead className="sticky top-0 bg-card">
                                            <tr className="border-b text-left text-muted-foreground">
                                                <th className="pb-2 pr-3 font-medium">
                                                    Move
                                                </th>
                                                <th className="pb-2 pr-3 font-medium">
                                                    Eval
                                                </th>
                                                <th className="pb-2 font-medium">
                                                    Loss
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                className={cn(
                                                    'cursor-pointer border-b',
                                                    selectedPly === 0 &&
                                                        'bg-muted/70',
                                                )}
                                                onClick={() => setSelectedPly(0)}
                                            >
                                                <td className="py-2 pr-3 font-medium">
                                                    Start
                                                </td>
                                                <td className="py-2 pr-3 tabular-nums">
                                                    —
                                                </td>
                                                <td className="py-2 tabular-nums">
                                                    —
                                                </td>
                                            </tr>
                                            {moves.map((move) => (
                                                <tr
                                                    key={move.id}
                                                    className={cn(
                                                        'cursor-pointer border-b last:border-0',
                                                        selectedPly === move.ply &&
                                                            'bg-muted/70',
                                                    )}
                                                    onClick={() =>
                                                        setSelectedPly(move.ply)
                                                    }
                                                >
                                                    <td className="py-2 pr-3 font-medium">
                                                        {formatMoveLabel(
                                                            move.ply,
                                                            move.move_number,
                                                            move.color,
                                                            move.san,
                                                        )}
                                                    </td>
                                                    <td className="py-2 pr-3 tabular-nums">
                                                        {formatEvaluation(
                                                            move.evaluation,
                                                            move.evaluation_type,
                                                        )}
                                                    </td>
                                                    <td
                                                        className={cn(
                                                            'py-2 tabular-nums',
                                                            move.eval_loss !== null &&
                                                                move.eval_loss > 100 &&
                                                                'font-medium text-destructive',
                                                        )}
                                                    >
                                                        {formatEvalLoss(
                                                            move.eval_loss,
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </>
    );
}
