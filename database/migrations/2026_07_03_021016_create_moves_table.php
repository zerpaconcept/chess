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
        Schema::create('moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('ply');
            $table->unsignedSmallInteger('move_number');
            $table->string('color', 1);
            $table->string('san');
            $table->string('uci', 5);
            $table->string('fen_after');
            $table->integer('evaluation')->nullable();
            $table->string('evaluation_type')->nullable();
            $table->string('best_move', 5)->nullable();
            $table->string('classification')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'ply']);
            $table->index('game_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moves');
    }
};
