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
            $table->string('user_id', 64)->index();
            $table->string('report', 191);
            $table->string('name', 100);
            $table->json('state')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'report', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    protected function table(): string
    {
        return (string) config('i-reports.presets.table', 'i_reports_presets');
    }
};
