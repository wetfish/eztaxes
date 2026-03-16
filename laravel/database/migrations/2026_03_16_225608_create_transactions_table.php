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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_year_id')->constrained('tax_years')->cascadeOnDelete();
            $table->foreignId('import_id')->constrained('imports')->cascadeOnDelete();
            $table->date('date');
            $table->string('description'); // raw text from bank/statement
            $table->decimal('amount', 10, 2); // signed: positive = income, negative = expense
            $table->json('raw_data')->nullable(); // full original CSV row for reference
            $table->string('match_type')->default('unmatched'); // expected: auto, manual, unmatched
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};