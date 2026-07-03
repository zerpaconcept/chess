<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->string('source_game_id')->nullable();
            $table->string('white_player');
            $table->string('black_player');
            $table->unsignedSmallInteger('white_elo')->nullable();
            $table->unsignedSmallInteger('black_elo')->nullable();
            $table->string('result', 8)->default('*');
            $table->string('event')->nullable();
            $table->string('site')->nullable();
            $table->date('played_at')->nullable();
            $table->string('time_control')->nullable();
            $table->string('eco', 3)->nullable();
            $table->string('opening')->nullable();
            $table->longText('pgn')->nullable();
            $table->string('initial_fen')->nullable();
            $table->string('analysis_status')->default('pending');
            $table->unsignedTinyInteger('analysis_depth')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('analysis_status');
            $table->unique(['source', 'source_game_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
