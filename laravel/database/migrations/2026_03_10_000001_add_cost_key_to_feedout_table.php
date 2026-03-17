<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Cost key: JSON key path in outgoing ping server response (e.g. "cost", "data.cpl")
     */
    public function up(): void
    {
        Schema::table('feedout', function (Blueprint $table) {
            $table->string('costKey', 100)->nullable()->after('costPerLeadOverride');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feedout', function (Blueprint $table) {
            $table->dropColumn('costKey');
        });
    }
};
