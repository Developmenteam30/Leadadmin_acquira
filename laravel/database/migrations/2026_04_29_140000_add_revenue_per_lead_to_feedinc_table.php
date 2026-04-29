<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedinc', function (Blueprint $table) {
            if (!Schema::hasColumn('feedinc', 'revenuePerLeadType')) {
                $table->string('revenuePerLeadType', 20)->default('fixed')->after('costPerLead');
            }
            if (!Schema::hasColumn('feedinc', 'revenuePerLead')) {
                $table->decimal('revenuePerLead', 10, 2)->unsigned()->default(0)->after('revenuePerLeadType');
            }
        });
    }

    public function down(): void
    {
        Schema::table('feedinc', function (Blueprint $table) {
            if (Schema::hasColumn('feedinc', 'revenuePerLead')) {
                $table->dropColumn('revenuePerLead');
            }
            if (Schema::hasColumn('feedinc', 'revenuePerLeadType')) {
                $table->dropColumn('revenuePerLeadType');
            }
        });
    }
};

