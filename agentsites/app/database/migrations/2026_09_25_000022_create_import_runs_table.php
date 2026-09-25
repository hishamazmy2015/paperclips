<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Spec §12 Import: the checkpoint table. Rows are processed in batches of 100 via the queue;
// last_row lets `platform:site:import --resume` continue after a kill.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 400);
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->jsonb('options')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('existing_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->unsignedInteger('last_row')->default(0);
            $table->string('errors_path', 400)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_runs');
    }
};
