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
        Schema::create('data_inbound', function (Blueprint $table) {
            $table->id('idRecord');
            $table->unsignedInteger('idFeedIn');
            $table->dateTime('timestamp')->useCurrent();
            $table->string('listcode', 255)->nullable();
            $table->string('url', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->dateTime('leadstamp')->nullable();
            $table->string('email', 255)->nullable();
            $table->string('fname', 50)->nullable();
            $table->string('lname', 50)->nullable();
            $table->string('addr', 150)->nullable();
            $table->string('addr2', 150)->nullable();
            $table->string('city', 75)->nullable();
            $table->string('state', 25)->nullable();
            $table->string('zip', 20)->nullable();
            $table->date('dob')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('landline', 20)->nullable();
            $table->string('cellphone', 20)->nullable();
            $table->string('country', 75)->nullable();
            $table->integer('jobId')->nullable();
            $table->string('result', 255)->nullable();
            $table->string('leadId', 255)->nullable();
            $table->string('custom1', 255)->nullable();
            $table->string('custom2', 255)->nullable();
            $table->string('custom3', 255)->nullable();
            $table->string('custom4', 255)->nullable();
            $table->string('custom5', 255)->nullable();
            $table->string('custom6', 255)->nullable();
            $table->json('customFields')->nullable();
            $table->unsignedTinyInteger('ping')->default(0);
            $table->json('rawData')->nullable();
            $table->unsignedTinyInteger('newping')->default(0);
            $table->decimal('cost', 10, 2)->nullable();

            $table->index('idFeedIn');
            $table->index('email');
            $table->index('cellphone');
            $table->index('landline');
            $table->index('url');
            $table->index('ip');
            $table->index(['idFeedIn', 'timestamp', 'result']);
            $table->index(['idFeedIn', 'result']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_inbound');
    }
};
