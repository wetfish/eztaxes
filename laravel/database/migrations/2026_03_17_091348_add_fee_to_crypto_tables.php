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
        Schema::table('crypto_buys', function (Blueprint $table) {
            $table->decimal('fee', 10, 2)->default(0)->after('total_cost'); // fee increases cost basis
        });

        Schema::table('crypto_sells', function (Blueprint $table) {
            $table->decimal('fee', 10, 2)->default(0)->after('total_proceeds'); // fee reduces proceeds
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crypto_buys', function (Blueprint $table) {
            $table->dropColumn('fee');
        });

        Schema::table('crypto_sells', function (Blueprint $table) {
            $table->dropColumn('fee');
        });
    }
};