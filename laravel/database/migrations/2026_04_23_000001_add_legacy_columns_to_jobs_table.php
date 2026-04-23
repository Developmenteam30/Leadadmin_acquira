<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add legacy columns used by scripts/process-jobs.php.
     * Keeps Laravel queue columns intact for queue:work compatibility.
     */
    public function up(): void
    {
        if (!Schema::hasTable('jobs')) {
            return;
        }

        Schema::table('jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('jobs', 'type')) {
                $table->string('type', 100)->nullable()->index();
            }
            if (!Schema::hasColumn('jobs', 'destination')) {
                $table->unsignedBigInteger('destination')->nullable()->index();
            }
            if (!Schema::hasColumn('jobs', 'fields')) {
                $table->longText('fields')->nullable();
            }
            if (!Schema::hasColumn('jobs', 'filename')) {
                $table->string('filename', 1000)->nullable();
            }
            if (!Schema::hasColumn('jobs', 'records')) {
                $table->unsignedInteger('records')->default(0);
            }
            if (!Schema::hasColumn('jobs', 'idUser')) {
                $table->unsignedBigInteger('idUser')->default(0)->index();
            }
            if (!Schema::hasColumn('jobs', 'status')) {
                $table->string('status', 30)->default('pending')->index();
            }
            if (!Schema::hasColumn('jobs', 'message')) {
                $table->text('message')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('jobs')) {
            return;
        }

        Schema::table('jobs', function (Blueprint $table) {
            $dropIfExists = function (string $column) use ($table): void {
                if (Schema::hasColumn('jobs', $column)) {
                    $table->dropColumn($column);
                }
            };

            $dropIfExists('type');
            $dropIfExists('destination');
            $dropIfExists('fields');
            $dropIfExists('filename');
            $dropIfExists('records');
            $dropIfExists('idUser');
            $dropIfExists('status');
            $dropIfExists('message');
        });
    }
};

