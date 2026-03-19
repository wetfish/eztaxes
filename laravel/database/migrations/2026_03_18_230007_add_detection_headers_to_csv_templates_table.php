<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('csv_templates', function (Blueprint $table) {
            $table->json('detection_headers')->nullable()->after('column_mapping');
            $table->boolean('is_seeded')->default(false)->after('detection_headers');
        });
    }

    public function down(): void
    {
        Schema::table('csv_templates', function (Blueprint $table) {
            $table->dropColumn(['detection_headers', 'is_seeded']);
        });
    }
};