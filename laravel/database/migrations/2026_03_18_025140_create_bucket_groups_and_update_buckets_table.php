<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bucket_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Replace parent_id with bucket_group_id on buckets
        if (Schema::hasColumn('buckets', 'parent_id')) {
            Schema::table('buckets', function (Blueprint $table) {
                $table->dropForeign(['parent_id']);
                $table->dropColumn('parent_id');
            });
        }

        Schema::table('buckets', function (Blueprint $table) {
            $table->foreignId('bucket_group_id')
                ->nullable()
                ->after('id')
                ->constrained('bucket_groups')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('buckets', function (Blueprint $table) {
            $table->dropForeign(['bucket_group_id']);
            $table->dropColumn('bucket_group_id');
        });

        Schema::dropIfExists('bucket_groups');

        Schema::table('buckets', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('id')
                ->constrained('buckets')
                ->nullOnDelete();
        });
    }
};