<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\Import\ChessComImportController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::get('games', [GameController::class, 'index'])->name('games.index');
    Route::post('games/{game}/analyze', [GameController::class, 'analyze'])->name('games.analyze');

    Route::get('import/chess-com', [ChessComImportController::class, 'create'])
        ->name('import.chess-com.create');
    Route::post('import/chess-com', [ChessComImportController::class, 'store'])
        ->name('import.chess-com.store');
});

require __DIR__.'/settings.php';
