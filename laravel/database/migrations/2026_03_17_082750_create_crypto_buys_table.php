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
        Schema::create('crypto_buys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crypto_asset_id')->constrained('crypto_assets')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('quantity', 16, 8); // amount of crypto purchased
            $table->decimal('cost_per_unit', 16, 2); // price paid per unit
            $table->decimal('total_cost', 12, 2); // quantity * cost_per_unit
            $table->decimal('quantity_remaining', 16, 8); // decreases as sells reference this buy
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crypto_buys');
    }
};