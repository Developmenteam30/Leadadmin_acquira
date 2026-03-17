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
        Schema::create('feedPopulation', function (Blueprint $table) {
            $table->id('idAssoc');
            $table->unsignedBigInteger('idFeedIn')->nullable();
            $table->unsignedBigInteger('idFeedOut')->nullable();
            $table->enum('enabled', ['0', '1'])->default('0');
            $table->enum('filterTypeUrl', ['reject', 'accept'])->nullable();
            $table->string('filterUrl', 5000)->nullable();
            $table->enum('filterTypeEmail', ['reject', 'accept'])->nullable();
            $table->string('filterEmail', 5000)->nullable();
            $table->enum('filterTypeListcode', ['reject', 'accept'])->nullable();
            $table->string('filterListcode', 1000)->nullable();
            $table->string('forceUrlList', 5000)->nullable();
            $table->tinyInteger('forceUrl')->default(0);
            $table->tinyInteger('livedata')->default(0);
            $table->tinyInteger('waterfall')->default(0);
            $table->unsignedSmallInteger('waterfallPriority')->default(0);
            $table->enum('queueType', ['queue', 'livedata', 'waterfall', 'waterfallLimit', 'waterfallLimitLive'])->default('queue');
            $table->date('startDate')->nullable();
            $table->enum('populationType', ['individual', 'category'])->default('individual');
            $table->string('feedCategory', 255)->nullable();
            $table->unsignedTinyInteger('isArchived')->default(0);

            $table->unique(['idFeedIn', 'idFeedOut']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedPopulation');
    }
};
