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
        Schema::create('crypto_buy_sell', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crypto_buy_id')->constrained('crypto_buys')->cascadeOnDelete();
            $table->foreignId('crypto_sell_id')->constrained('crypto_sells')->cascadeOnDelete();
            $table->decimal('quantity', 16, 8); // how much of this buy was used for this sell
            $table->decimal('cost_basis', 12, 2); // quantity * buy's cost_per_unit
            $table->boolean('is_long_term')->default(false); // auto-calculated: buy date > 1 year before sell date
            $table->timestamps();

            $table->unique(['crypto_buy_id', 'crypto_sell_id']); // prevent duplicate allocations
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crypto_buy_sell');
    }
};