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
        Schema::create('aktivitas_peserta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peserta_id')->constrained('peserta')->onDelete('cascade');
            $table->foreignId('ujian_id')->constrained('ujian')->onDelete('cascade');
            $table->enum('status', ['belum_login', 'sedang_mengerjakan', 'sudah_submit'])->default('belum_login');
            $table->datetime('waktu_login')->nullable();
            $table->datetime('waktu_submit')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aktivitas_peserta');
    }
};