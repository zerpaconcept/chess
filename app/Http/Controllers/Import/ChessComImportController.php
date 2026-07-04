<?php

namespace App\Http\Controllers\Import;

use App\Enums\ChessComTimeClass;
use App\Exceptions\ChessCom\PlayerNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Import\ImportChessComGamesRequest;
use App\Services\ChessCom\ImportChessComGames;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ChessComImportController extends Controller
{
    /**
     * Show the Chess.com import form.
     */
    public function create(): Response
    {
        return Inertia::render('import/chess-com', [
            'timeClassOptions' => collect(ChessComTimeClass::cases())
                ->map(fn (ChessComTimeClass $timeClass) => [
                    'value' => $timeClass->value,
                    'label' => ucfirst($timeClass->value),
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Import games from Chess.com for the authenticated user.
     */
    public function store(
        ImportChessComGamesRequest $request,
        ImportChessComGames $importer,
    ): RedirectResponse {
        try {
            $result = $importer->import(
                user: $request->user(),
                username: $request->username(),
                fromDate: Carbon::parse($request->validated('from_date')),
                toDate: Carbon::parse($request->validated('to_date')),
                timeClasses: $request->timeClasses(),
                color: $request->colorFilter(),
            );
        } catch (PlayerNotFoundException) {
            return back()->withErrors([
                'username' => __('No Chess.com player was found with that username.'),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Imported :imported games (:skipped already existed).', [
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
            ]),
        ]);

        return to_route('import.chess-com.create');
    }
}
