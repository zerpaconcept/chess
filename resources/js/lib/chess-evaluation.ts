export type EvaluationType = 'cp' | 'mate';

export type EvaluationPoint = {
    ply: number;
    evaluation: number;
    evaluationType: EvaluationType;
};

const MATE_GRAPH_CAP = 1000;

export function evaluationToGraphValue(
    evaluation: number,
    evaluationType: EvaluationType,
): number {
    if (evaluationType === 'mate') {
        const sign = evaluation >= 0 ? 1 : -1;

        return sign * (MATE_GRAPH_CAP - Math.min(Math.abs(evaluation), 10) * 50);
    }

    return Math.max(-MATE_GRAPH_CAP, Math.min(MATE_GRAPH_CAP, evaluation));
}

export function formatEvaluation(
    evaluation: number | null,
    evaluationType: EvaluationType | null,
): string {
    if (evaluation === null || evaluationType === null) {
        return '—';
    }

    if (evaluationType === 'mate') {
        const prefix = evaluation > 0 ? '+' : evaluation < 0 ? '-' : '';

        return `${prefix}M${Math.abs(evaluation)}`;
    }

    const pawns = evaluation / 100;
    const prefix = pawns > 0 ? '+' : '';

    return `${prefix}${pawns.toFixed(2)}`;
}

export function formatEvalLoss(evalLoss: number | null): string {
    if (evalLoss === null) {
        return '—';
    }

    if (evalLoss === 0) {
        return '0.00';
    }

    const pawns = evalLoss / 100;
    const prefix = pawns > 0 ? '+' : '';

    return `${prefix}${pawns.toFixed(2)}`;
}

export function formatMoveLabel(ply: number, moveNumber: number, color: string, san: string): string {
    if (color === 'w') {
        return `${moveNumber}. ${san}`;
    }

    return `${moveNumber}... ${san}`;
}

export function graphScale(points: EvaluationPoint[]): number {
    const values = points.map((point) =>
        Math.abs(evaluationToGraphValue(point.evaluation, point.evaluationType)),
    );

    return Math.max(100, ...values, 50);
}
