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
        Schema::create('crypto_sells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crypto_asset_id')->constrained('crypto_assets')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('quantity', 16, 8); // amount of crypto sold
            $table->decimal('price_per_unit', 16, 2); // price received per unit
            $table->decimal('total_proceeds', 12, 2); // quantity * price_per_unit
            $table->decimal('total_cost_basis', 12, 2)->default(0); // sum of cost basis from referenced buys
            $table->decimal('gain_loss', 12, 2)->default(0); // total_proceeds - total_cost_basis
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crypto_sells');
    }
};