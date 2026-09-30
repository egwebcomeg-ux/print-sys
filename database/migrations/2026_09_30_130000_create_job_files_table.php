<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime', 150)->nullable();
            $table->unsignedBigInteger('size');
            // Re-uploading a file with the same name bumps its version.
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['job_id', 'original_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_files');
    }
};
