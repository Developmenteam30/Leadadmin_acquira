<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_outbound', function (Blueprint $table) {
            $table->unsignedInteger('sentCount')->default(0)->after('accepted');
        });

        // Best-effort backfill for rows that clearly show at least one prior send.
        DB::table('data_outbound')
            ->where(function ($q) {
                $q->where('processed', 1)
                    ->orWhereNotNull('result')
                    ->orWhere('accepted', 1)
                    ->orWhereNotNull('timestamp');
            })
            ->update(['sentCount' => 1]);
    }

    public function down(): void
    {
        Schema::table('data_outbound', function (Blueprint $table) {
            $table->dropColumn('sentCount');
        });
    }
};
