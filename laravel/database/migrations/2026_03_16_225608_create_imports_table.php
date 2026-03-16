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
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_year_id')->constrained('tax_years')->cascadeOnDelete();
            $table->foreignId('csv_template_id')->nullable()->constrained('csv_templates')->nullOnDelete();
            $table->string('original_filename'); // as uploaded, e.g. "Accounts-Combined-2024.csv"
            $table->integer('rows_total')->default(0);
            $table->integer('rows_matched')->default(0);
            $table->integer('rows_unmatched')->default(0);
            $table->integer('rows_ignored')->default(0);
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};