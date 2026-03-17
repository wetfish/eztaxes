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
        Schema::create('balance_sheet_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_year_id')->constrained('tax_years')->cascadeOnDelete();
            $table->foreignId('crypto_asset_id')->nullable()->constrained('crypto_assets')->nullOnDelete();
            $table->string('label'); // e.g. "Bitcoin", "Business Checking", "AAPL Stock"
            $table->string('asset_type'); // expected: crypto, stock, cash, other
            $table->decimal('quantity', 16, 8)->nullable(); // for countable assets like crypto/stock
            $table->decimal('unit_price_year_end', 16, 2)->nullable(); // Dec 31 price per unit
            $table->decimal('total_value', 12, 2)->default(0); // quantity * unit_price, or manual entry for cash
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balance_sheet_items');
    }
};