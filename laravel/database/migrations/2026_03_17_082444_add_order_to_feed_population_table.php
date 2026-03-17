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
        Schema::table('feedPopulation', function (Blueprint $table) {
            $table->unsignedSmallInteger('order')->nullable()->default(1)->after('waterfallPriority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feedPopulation', function (Blueprint $table) {
            $table->dropColumn('order');
        });
    }
};
