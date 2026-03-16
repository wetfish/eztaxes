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
        Schema::create('csv_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "Local Credit Union Checking", "Chase Credit Card"
            $table->json('column_mapping'); // e.g. {"date": "Posting Date", "amount": "Amount", "description": "Description"}
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('csv_templates');
    }
};