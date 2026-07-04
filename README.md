# Chess Analyzer

A personal chess analysis app for importing games, reviewing them move by move, and running engine evaluation with a **local Stockfish** installation.

The goal is to build a full-featured chess analyzer with imports from **Chess.com** and **Lichess**, interactive game review, and Stockfish-powered insights — without relying on cloud engine services.

## Features

### Available now

- **Chess.com import** — download games by username, date range, time control, and color
- **Game library** — browse imported games and queue analysis on demand
- **Local Stockfish analysis** — parses PGN moves and evaluates each position via UCI
- **Game review** — interactive board, move list, evaluation graph, centipawn loss per move

### Planned

- **Lichess import** — bring in games from Lichess accounts and PGN exports
- Move classifications (blunder, mistake, inaccuracy, etc.)
- Deeper review tools (opening explorer, report summaries, re-analysis controls)

## How it works

1. **Import** — games are stored with metadata and PGN. Import is fast; moves are not parsed yet.
2. **Analyze** — clicking **Analyze** queues a background job that:
   - parses the PGN into individual moves
   - runs Stockfish on the starting position and after each move
   - stores evaluations, best moves, and eval loss
3. **Review** — open a game to step through moves on the board, inspect the eval graph, and see engine scores.

Analysis requires a **queue worker** and a **Stockfish binary** on your machine.

## Tech stack

- **Backend:** Laravel 13, Fortify (auth), Inertia Laravel
- **Frontend:** React 19, Inertia.js v3, Tailwind CSS v4
- **Chess:** `chess.js`, `react-chessboard`, `cmuset/chess-tools` (PGN parsing)
- **Engine:** Stockfish (local executable via UCI)

## Requirements

- PHP 8.4+
- Composer
- Node.js 20+
- MySQL, PostgreSQL, or SQLite
- [Stockfish](https://stockfishchess.org/download/) installed locally

## Setup

```bash
# Install dependencies
composer install
npm install

# Environment
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate

# Frontend
npm run dev
```

Or use the combined dev script (app server, queue, logs, Vite):

```bash
composer run dev
```

### Stockfish

Point the app at your local Stockfish executable in `.env`:

```env
STOCKFISH_PATH="C:\\Program Files\\stockfish\\stockfish-windows-x86-64-avx2.exe"
STOCKFISH_DEPTH=18
STOCKFISH_MOVETIME_MS=500
STOCKFISH_TIMEOUT_SECONDS=30
```

On Windows, backslashes inside double quotes must be escaped:

```env
STOCKFISH_PATH="C:\\Program Files\\stockfish\\stockfish-windows-x86-64-avx2.exe"
```

On macOS or Linux, a forward-slash path also works:

```env
STOCKFISH_PATH="/usr/local/bin/stockfish"
```

### Queue

Analysis runs asynchronously. Use a database queue (default in `.env.example`):

```env
QUEUE_CONNECTION=database
```

Run a worker:

```bash
php artisan queue:work
```

If analysis jobs stay pending, the queue worker is likely not running.

## Testing

```bash
php artisan test
```

## License

MIT
