<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Stores the cost returned by the outgoing ping server for each record.
     */
    public function up(): void
    {
        Schema::table('data_outbound', function (Blueprint $table) {
            $table->decimal('cost', 10, 4)->nullable()->after('result');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_outbound', function (Blueprint $table) {
            $table->dropColumn('cost');
        });
    }
};
