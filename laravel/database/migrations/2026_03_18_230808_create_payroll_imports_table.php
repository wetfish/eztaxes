<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('csv_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');  // employee, us_contractor, intl_contractor
            $table->string('original_filename');
            $table->integer('rows_total')->default(0);
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_imports');
    }
};