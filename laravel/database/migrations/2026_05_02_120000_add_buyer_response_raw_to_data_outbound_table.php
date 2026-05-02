<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Raw HTTP body / buyer payload as returned over the wire (separate from normalized outbound result text).
     */
    public function up(): void
    {
        Schema::table('data_outbound', function (Blueprint $table) {
            $table->longText('buyer_response_raw')->nullable()->after('result');
        });
    }

    public function down(): void
    {
        Schema::table('data_outbound', function (Blueprint $table) {
            $table->dropColumn('buyer_response_raw');
        });
    }
};
