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
        Schema::create('data_outbound', function (Blueprint $table) {
            $table->unsignedBigInteger('idRecord');
            $table->unsignedInteger('idFeedIn');
            $table->unsignedInteger('idFeedOut');
            $table->dateTime('timestamp')->nullable();
            $table->text('result')->nullable();
            $table->unsignedBigInteger('idRecordLegacy')->nullable();
            $table->unsignedTinyInteger('processed')->default(0);
            $table->unsignedTinyInteger('isBillable')->default(1);
            $table->string('url', 255)->nullable();
            $table->unsignedTinyInteger('accepted')->default(0);

            $table->unique(['idRecord', 'idFeedOut']);
            $table->unique(['idRecordLegacy', 'idFeedOut']);
            $table->index('idFeedOut');
            $table->index(['idFeedOut', 'processed']);
            $table->index(['idFeedOut', 'processed', 'accepted']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_outbound');
    }
};
