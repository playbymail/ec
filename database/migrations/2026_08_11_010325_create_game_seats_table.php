<?php

use App\Enums\GameRole;
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
        Schema::create('game_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default(GameRole::Player->value);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // One seat per account per game. A seat that is no longer played is
            // retired with is_active rather than deleted, so the row — and the
            // engine history pointing at it — survives.
            $table->unique(['game_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_seats');
    }
};
