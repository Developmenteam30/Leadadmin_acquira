<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Insert United States (id 236) which is the default country
        DB::table('countries')->insertOrIgnore([
            'id' => 236,
            'short_name' => 'United States',
            'alpha2_code' => 'US',
            'alpha3_code' => 'USA',
            'numeric_code' => 840,
        ]);

        // Insert a few other common countries
        $commonCountries = [
            ['id' => 1, 'short_name' => 'Canada', 'alpha2_code' => 'CA', 'alpha3_code' => 'CAN', 'numeric_code' => 124],
            ['id' => 2, 'short_name' => 'United Kingdom', 'alpha2_code' => 'GB', 'alpha3_code' => 'GBR', 'numeric_code' => 826],
            ['id' => 3, 'short_name' => 'Australia', 'alpha2_code' => 'AU', 'alpha3_code' => 'AUS', 'numeric_code' => 36],
            ['id' => 4, 'short_name' => 'Mexico', 'alpha2_code' => 'MX', 'alpha3_code' => 'MEX', 'numeric_code' => 484],
        ];

        foreach ($commonCountries as $country) {
            DB::table('countries')->insertOrIgnore($country);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optionally remove the seeded countries
        // DB::table('countries')->whereIn('id', [236, 1, 2, 3, 4])->delete();
    }
};
