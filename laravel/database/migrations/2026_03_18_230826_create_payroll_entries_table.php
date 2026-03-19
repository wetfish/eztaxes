<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_import_id')->constrained()->cascadeOnDelete();
            $table->string('type');  // employee, us_contractor, intl_contractor
            $table->string('name');
            $table->date('date')->nullable();

            // Employee payroll fields (from Gusto custom report)
            $table->decimal('gross_pay', 10, 2)->default(0);
            $table->decimal('employee_deductions', 10, 2)->default(0);
            $table->decimal('employer_contributions', 10, 2)->default(0);
            $table->decimal('employee_taxes', 10, 2)->default(0);
            $table->decimal('employer_taxes', 10, 2)->default(0);
            $table->decimal('net_pay', 10, 2)->default(0);
            $table->decimal('employer_cost', 10, 2)->default(0);
            $table->decimal('check_amount', 10, 2)->default(0);

            // International contractor fields
            $table->string('wage_type')->nullable();
            $table->string('currency')->nullable();
            $table->decimal('foreign_amount', 10, 2)->nullable();
            $table->string('payment_status')->nullable();
            $table->decimal('hours', 8, 2)->nullable();
            $table->decimal('hourly_rate', 8, 2)->nullable();

            // US contractor fields
            $table->string('department')->nullable();
            $table->decimal('tips_payment', 10, 2)->nullable();
            $table->decimal('tips_cash', 10, 2)->nullable();

            // Shared
            $table->string('notes')->nullable();  // Pay period info, memo, etc.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_entries');
    }
};