import GameController from '@/actions/App/Http/Controllers/GameController';
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
import { index as gamesIndex } from '@/routes/games';
import { Head, Link, router } from '@inertiajs/react';
import { Cpu } from 'lucide-react';

type GameListItem = {
    id: number;
    white_player: string;
    black_player: string;
    white_elo: number | null;
    black_elo: number | null;
    result: string;
    source: string;
    played_at: string | null;
    opening: string | null;
    analysis_status: 'pending' | 'in_progress' | 'completed' | 'failed';
    analysis_depth: number | null;
    analyzed_at: string | null;
    moves_count: number;
    can_analyze: boolean;
};

type PaginatedGames = {
    data: GameListItem[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const statusLabels: Record<GameListItem['analysis_status'], string> = {
    pending: 'Pending',
    in_progress: 'Analyzing',
    completed: 'Analyzed',
    failed: 'Failed',
};

const statusVariants: Record<
    GameListItem['analysis_status'],
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    pending: 'outline',
    in_progress: 'secondary',
    completed: 'default',
    failed: 'destructive',
};

function formatPlayers(game: GameListItem): string {
    const white = game.white_elo
        ? `${game.white_player} (${game.white_elo})`
        : game.white_player;
    const black = game.black_elo
        ? `${game.black_player} (${game.black_elo})`
        : game.black_player;

    return `${white} vs ${black}`;
}

export default function GamesIndex({ games }: { games: PaginatedGames }) {
    function analyze(game: GameListItem): void {
        router.post(GameController.analyze.url(game.id), {}, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Games" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Games"
                    description="Imported games are analyzed on demand with Stockfish. Moves are parsed when analysis runs."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Your library</CardTitle>
                        <CardDescription>
                            {games.data.length === 0
                                ? 'No games yet. Import games from Chess.com to get started.'
                                : 'Click Analyze to parse the PGN and run engine evaluation on each move.'}
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        {games.data.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Use the Import Chess.com page to download games.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[640px] text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="pb-3 pr-4 font-medium">
                                                Players
                                            </th>
                                            <th className="pb-3 pr-4 font-medium">
                                                Date
                                            </th>
                                            <th className="pb-3 pr-4 font-medium">
                                                Result
                                            </th>
                                            <th className="pb-3 pr-4 font-medium">
                                                Source
                                            </th>
                                            <th className="pb-3 pr-4 font-medium">
                                                Analysis
                                            </th>
                                            <th className="pb-3 font-medium">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {games.data.map((game) => (
                                            <tr
                                                key={game.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="py-3 pr-4">
                                                    <div className="font-medium">
                                                        {formatPlayers(game)}
                                                    </div>
                                                    {game.opening && (
                                                        <div className="text-xs text-muted-foreground">
                                                            {game.opening}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="py-3 pr-4">
                                                    {game.played_at ?? '—'}
                                                </td>
                                                <td className="py-3 pr-4">
                                                    {game.result}
                                                </td>
                                                <td className="py-3 pr-4 capitalize">
                                                    {game.source.replace('_', ' ')}
                                                </td>
                                                <td className="py-3 pr-4">
                                                    <div className="flex flex-col gap-1">
                                                        <Badge
                                                            variant={
                                                                statusVariants[
                                                                    game
                                                                        .analysis_status
                                                                ]
                                                            }
                                                        >
                                                            {
                                                                statusLabels[
                                                                    game
                                                                        .analysis_status
                                                                ]
                                                            }
                                                        </Badge>
                                                        {game.analysis_status ===
                                                            'completed' && (
                                                            <span className="text-xs text-muted-foreground">
                                                                {game.moves_count}{' '}
                                                                moves · depth{' '}
                                                                {game.analysis_depth}
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="py-3">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        disabled={
                                                            !game.can_analyze
                                                        }
                                                        onClick={() =>
                                                            analyze(game)
                                                        }
                                                    >
                                                        <Cpu className="size-4" />
                                                        {game.analysis_status ===
                                                        'in_progress'
                                                            ? 'Analyzing...'
                                                            : game.analysis_status ===
                                                                'completed'
                                                              ? 'Re-analyze'
                                                              : 'Analyze'}
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {games.links.length > 3 && (
                            <div className="mt-6 flex flex-wrap gap-2">
                                {games.links.map((link, index) =>
                                    link.url ? (
                                        <Link
                                            key={`${link.label}-${index}`}
                                            href={link.url}
                                            preserveScroll
                                            className={`rounded-md border px-3 py-1 text-sm ${
                                                link.active
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'hover:bg-muted'
                                            }`}
                                        >
                                            <span
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                            />
                                        </Link>
                                    ) : (
                                        <span
                                            key={`${link.label}-${index}`}
                                            className="rounded-md border px-3 py-1 text-sm text-muted-foreground"
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    ),
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

GamesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Games',
            href: gamesIndex(),
        },
    ],
};
