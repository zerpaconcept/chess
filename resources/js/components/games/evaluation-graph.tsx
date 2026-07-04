import { useMemo, useState } from 'react';
import {
    evaluationToGraphValue,
    formatEvaluation,
    graphScale,
    type EvaluationPoint,
} from '@/lib/chess-evaluation';
import { cn } from '@/lib/utils';

type EvaluationGraphProps = {
    points: EvaluationPoint[];
    selectedPly: number | null;
    onSelectPly: (ply: number) => void;
    className?: string;
};

const WIDTH = 640;
const HEIGHT = 220;
const PADDING = { top: 16, right: 16, bottom: 28, left: 44 };

export default function EvaluationGraph({
    points,
    selectedPly,
    onSelectPly,
    className,
}: EvaluationGraphProps) {
    const [hoveredPly, setHoveredPly] = useState<number | null>(null);

    const chart = useMemo(() => {
        if (points.length === 0) {
            return null;
        }

        const scale = graphScale(points);
        const innerWidth = WIDTH - PADDING.left - PADDING.right;
        const innerHeight = HEIGHT - PADDING.top - PADDING.bottom;
        const maxPly = Math.max(...points.map((point) => point.ply));

        const xForPly = (ply: number): number =>
            PADDING.left + ((ply - 0.5) / maxPly) * innerWidth;

        const yForValue = (value: number): number => {
            const mid = PADDING.top + innerHeight / 2;

            return mid - (value / scale) * (innerHeight / 2);
        };

        const plotted = points.map((point) => {
            const value = evaluationToGraphValue(
                point.evaluation,
                point.evaluationType,
            );

            return {
                ...point,
                value,
                x: xForPly(point.ply),
                y: yForValue(value),
            };
        });

        const zeroY = yForValue(0);
        const areaPath = [
            `M ${PADDING.left} ${zeroY}`,
            ...plotted.map((point) => `L ${point.x} ${point.y}`),
            `L ${plotted.at(-1)?.x ?? PADDING.left} ${zeroY}`,
            'Z',
        ].join(' ');

        const linePath = plotted
            .map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`)
            .join(' ');

        const yTicks = [-scale, -scale / 2, 0, scale / 2, scale];

        return {
            plotted,
            areaPath,
            linePath,
            zeroY,
            scale,
            maxPly,
            xForPly,
            yForValue,
            yTicks,
            innerWidth,
            innerHeight,
        };
    }, [points]);

    if (!chart) {
        return (
            <div
                className={cn(
                    'flex h-[220px] items-center justify-center rounded-lg border border-dashed text-sm text-muted-foreground',
                    className,
                )}
            >
                No evaluation data to chart yet.
            </div>
        );
    }

    const activePly = hoveredPly ?? selectedPly;
    const activePoint = chart.plotted.find((point) => point.ply === activePly);

    return (
        <div className={cn('space-y-2', className)}>
            <div className="flex items-center justify-between text-xs text-muted-foreground">
                <span>White advantage</span>
                {activePoint ? (
                    <span className="font-medium text-foreground">
                        Ply {activePoint.ply}:{' '}
                        {formatEvaluation(
                            activePoint.evaluation,
                            activePoint.evaluationType,
                        )}
                    </span>
                ) : (
                    <span>Hover or select a move</span>
                )}
            </div>

            <svg
                viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
                className="h-auto w-full rounded-lg border bg-card"
                role="img"
                aria-label="Evaluation graph"
            >
                <defs>
                    <linearGradient id="eval-gradient" x1="0" x2="0" y1="0" y2="1">
                        <stop offset="0%" stopColor="rgb(255 255 255 / 0.55)" />
                        <stop offset="50%" stopColor="rgb(255 255 255 / 0.08)" />
                        <stop offset="50%" stopColor="rgb(23 23 23 / 0.08)" />
                        <stop offset="100%" stopColor="rgb(23 23 23 / 0.45)" />
                    </linearGradient>
                </defs>

                {chart.yTicks.map((tick) => {
                    const y = chart.yForValue(tick);

                    return (
                        <g key={tick}>
                            <line
                                x1={PADDING.left}
                                x2={WIDTH - PADDING.right}
                                y1={y}
                                y2={y}
                                className="stroke-border"
                                strokeDasharray={tick === 0 ? undefined : '4 4'}
                            />
                            <text
                                x={PADDING.left - 8}
                                y={y + 4}
                                textAnchor="end"
                                className="fill-muted-foreground text-[10px]"
                            >
                                {tick === 0
                                    ? '0'
                                    : `${tick > 0 ? '+' : ''}${(tick / 100).toFixed(1)}`}
                            </text>
                        </g>
                    );
                })}

                <path d={chart.areaPath} fill="url(#eval-gradient)" />

                <line
                    x1={PADDING.left}
                    x2={WIDTH - PADDING.right}
                    y1={chart.zeroY}
                    y2={chart.zeroY}
                    className="stroke-foreground/40"
                />

                <path
                    d={chart.linePath}
                    fill="none"
                    className="stroke-foreground"
                    strokeWidth="2"
                />

                {chart.plotted.map((point) => (
                    <g key={point.ply}>
                        <circle
                            cx={point.x}
                            cy={point.y}
                            r={activePly === point.ply ? 5 : 3}
                            className={cn(
                                'cursor-pointer transition-all',
                                activePly === point.ply
                                    ? 'fill-primary stroke-background stroke-2'
                                    : 'fill-foreground/80 hover:fill-primary',
                            )}
                            onMouseEnter={() => setHoveredPly(point.ply)}
                            onMouseLeave={() => setHoveredPly(null)}
                            onClick={() => onSelectPly(point.ply)}
                        />
                        <rect
                            x={point.x - 8}
                            y={PADDING.top}
                            width={16}
                            height={HEIGHT - PADDING.top - PADDING.bottom}
                            fill="transparent"
                            className="cursor-pointer"
                            onMouseEnter={() => setHoveredPly(point.ply)}
                            onMouseLeave={() => setHoveredPly(null)}
                            onClick={() => onSelectPly(point.ply)}
                        />
                    </g>
                ))}

                {activePoint && (
                    <line
                        x1={activePoint.x}
                        x2={activePoint.x}
                        y1={PADDING.top}
                        y2={HEIGHT - PADDING.bottom}
                        className="stroke-primary/60"
                        strokeDasharray="4 4"
                    />
                )}

                {[1, Math.ceil(chart.maxPly / 2), chart.maxPly].map((ply) => (
                    <text
                        key={ply}
                        x={chart.xForPly(ply)}
                        y={HEIGHT - 8}
                        textAnchor="middle"
                        className="fill-muted-foreground text-[10px]"
                    >
                        {Math.ceil(ply / 2)}
                    </text>
                ))}
            </svg>
        </div>
    );
}
