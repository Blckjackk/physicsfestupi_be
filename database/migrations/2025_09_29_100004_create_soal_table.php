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
        Schema::create('soal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ujian_id')->constrained('ujian')->onDelete('cascade');
            $table->integer('nomor_soal');
            $table->enum('tipe_soal', ['text', 'gambar'])->default('text');
            $table->text('deskripsi_soal')->nullable();
            $table->text('pertanyaan');
            $table->string('media_soal', 255)->nullable();
            $table->text('opsi_a')->nullable();
            $table->string('opsi_a_media', 255)->nullable();
            $table->text('opsi_b')->nullable();
            $table->string('opsi_b_media', 255)->nullable();
            $table->text('opsi_c')->nullable();
            $table->string('opsi_c_media', 255)->nullable();
            $table->text('opsi_d')->nullable();
            $table->string('opsi_d_media', 255)->nullable();
            $table->text('opsi_e')->nullable();
            $table->string('opsi_e_media', 255)->nullable();
            $table->char('jawaban_benar', 1)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('soal');
    }
};