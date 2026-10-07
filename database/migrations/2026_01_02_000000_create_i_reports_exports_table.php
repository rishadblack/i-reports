<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table) {
            $table->id();
            $table->string('owner', 120)->index();
            $table->string('report', 191);
            $table->string('format', 10);
            $table->string('status', 20)->default('queued')->index();
            $table->string('disk', 64);
            $table->string('path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->json('request')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    protected function table(): string
    {
        return (string) config('i-reports.queue.table', 'i_reports_exports');
    }
};
